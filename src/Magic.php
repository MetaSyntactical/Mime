<?php
/*
 * This file is part of the MetaSyntactical/Mime component.
 *
 * (c) Daniel Kreuer <d.kreuer@danielkreuer.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MetaSyntactical\Mime;

use MetaSyntactical\Io\FileReader;
use MetaSyntactical\Mime\Exception\FileNotFoundException;

/**
 * This class is used to classify the given file using some magic bytes
 * characteristic to a particular file type. The classification information can
 * be a MIME type or just text describing the file.
 *
 * This method is slower than determining the type by file suffix but on the
 * other hand reduces the risk of fail positives during the test.
 *
 * The magic file consists of ASCII characters defining the magic numbers for
 * different file types. Each row has 4 to 5 columns, empty and commented lines
 * (those starting with a hash character) are ignored. Columns are described
 * below.
 *
 *  o <b>1</b> -- byte number to begin checking from. '>' indicates a dependency
 *    upon the previous non-'>' line
 *  o <b>2</b> -- type of data to match. Can be one of following
 *    - <i>byte</i> (single character)
 *    - <i>short</i> (machine-order 16-bit integer)
 *    - <i>long</i> (machine-order 32-bit integer)
 *    - <i>string</i> (arbitrary-length string)
 *    - <i>date</i> (long integer date (seconds since Unix epoch/1970))
 *    - <i>beshort</i> (big-endian 16-bit integer)
 *    - <i>belong</i> (big-endian 32-bit integer)
 *    - <i>bedate</i> (big-endian 32-bit integer date)
 *    - <i>leshort</i> (little-endian 16-bit integer)
 *    - <i>lelong</i> (little-endian 32-bit integer)
 *    - <i>ledate</i> (little-endian 32-bit integer date)
 *  o <b>3</b> -- contents of data to match
 *  o <b>4</b> -- file description/MIME type if matched
 *  o <b>5</b> -- optional MIME encoding if matched and if above was a MIME type
 *
 * @author Daniel Kreuer <d.kreuer@danielkreuer.com>
 */
final class Magic
{
    /**
     * @var string Magic Numbers data
     */
    private $magic;

    /**
     * @var string Path to default magic file
     */
    private static $defaultMagicFile;

    /**
     * Inject default magic file to be used on instantiating class.
     *
     * @param string $filePath path to the magic file to be used, defaults to shipped data file
     * @throws Exception\FileNotFoundException if specified filePath does not exist or is not readable
     */
    public static function setDefaultMagicFile($filePath = null)
    {
        if (!is_null($filePath) && !file_exists($filePath)) {
            throw new FileNotFoundException('File does not exist or is not readable: ' . $filePath);
        }
        self::$defaultMagicFile = $filePath;
    }

    /**
     * Constructor.
     *
     * Reads the magic information from given magic file.
     *
     * @param string $filePath path to the magic file to be used, defaults to shipped data file
     * @throws FileNotFoundException if specified filePath does not exist or is not readable
     */
    public function __construct($filePath = null)
    {
        $filePath = $filePath ?: self::$defaultMagicFile ?: __DIR__ . '/_Data/magic';
        if (!file_exists($filePath)) {
            throw new FileNotFoundException('File does not exist or is not readable: ' . $filePath);
        }

        $reader = new FileReader($filePath);
        $this->magic = $reader->read($reader->getSize());
    }

    /**
     * Returns the recognized MIME type/description of the given file. The type
     * is determined by the content using magic bytes characteristic for the
     * particular file type.
     *
     * If the type could not be found, the function returns the default value,
     * or <var>null</var>.
     *
     * @param string $filename The file path whose type to determine.
     * @param string $default  The default value.
     * @return string|boolean
     */
    public function getMimeType($filename, $default = null)
    {
        $reader = new FileReader($filename);

        $parentOffset = 0;
        $regexp = "/^(?P<Dependant>>?)(?P<Byte>\\d+)\\s+(?P<MatchType"
                . ">\\S+)\\s+(?P<MatchData>\\S+)(?:\\s+(?P<MIMEType>[a-"
                . "z]+\\/[a-z-0-9\.]+)?(?:\\s+(?P<Description>.?+))?)?$/";
        foreach (preg_split('/^/m', $this->magic) as $line) {
            $chunks = array();
            if (!preg_match($regexp, $line, $chunks)) {
                continue;
            }

            if ($chunks['Dependant']) {
                $reader->setOffset($parentOffset);
                $reader->skip($chunks['Byte']);
            } else {
                $reader->setOffset($parentOffset = $chunks['Byte']);
            }

            $matchType = strtolower($chunks['MatchType']);
            $matchData = preg_replace_callback_array(
                [
                    "/\\\\ /" => function() { return " "; },
                    "/\\\\\\\\/" => function() { return "\\\\"; },
                    "/\\\\([0-7]{1,3})/" => function($match) { return pack("H*", base_convert($match[1], 8, 16)); },
                    "/\\\\x([0-9A-Fa-f]{1,2})/" => function ($match) { return pack("H*", $match[1]); },
                    "/0x([0-9A-Fa-f]+)/" => function ($match) { return hexdec($match[1]); },
                ],
                $chunks["MatchData"]
            );

            switch ($matchType) {
                case 'byte':    // single character
                    $data = $reader->readInt8();
                    break;
                case 'short':   // machine-order 16-bit integer
                    $data = $reader->readInt16();
                    break;
                case 'long':    // machine-order 32-bit integer
                    $data = $reader->readInt32();
                    break;
                case 'string':  // arbitrary-length string
                    $data = $reader->readString8(strlen($matchData));
                    break;
                case 'date':    // long integer date (seconds since Unix epoch)
                    $data = $reader->readInt64BE();
                    break;
                case 'beshort': // big-endian 16-bit integer
                    $data = $reader->readUInt16BE();
                    break;
                case 'belong':  // big-endian 32-bit integer
                    // break intentionally omitted
                case 'bedate':  // big-endian 32-bit integer date
                    $data = $reader->readUInt32BE();
                    break;
                case 'leshort': // little-endian 16-bit integer
                    $data = $reader->readUInt16LE();
                    break;
                case 'lelong':  // little-endian 32-bit integer
                    // break intentionally omitted
                case 'ledate':  // little-endian 32-bit integer date
                    $data = $reader->readUInt32LE();
                    break;
                default:
                    $data = null;
                    break;
            }

            if (strcmp($data, $matchData) == 0) {
                if (!empty($chunks['MIMEType'])) {
                    return $chunks['MIMEType'];
                }
                if (!empty($chunks['Description'])) {
                    return rtrim($chunks['Description'], "\n");
                }
            }
        }
        return $default;
    }

    /**
     * Returns the results of the mime type check either as a boolean or an
     * array of boolean values.
     *
     * @param string|array $filename The file path whose type to test.
     * @param string|array $mimeType The mime type to test against.
     * @return boolean|array
     */
    public function isMimeType($filename, $mimeType)
    {
        if (is_array($filename)) {
            $result = array();
            foreach ($filename as $key => $value) {
                $result[] = ($this->getMimeType($value) == (is_array($mimeType) ? $mimeType[$key] : $mimeType))
                            ? true
                            : false;
            }
            return $result;
        } else {
            return $this->getMimeType($filename) == $mimeType ? true : false;
        }
    }
}
