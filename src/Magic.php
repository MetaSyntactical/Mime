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
     * Magic Numbers data
     */
    private string $magic;

    /**
     * Path to default magic file
     */
    private static ?string $defaultMagicFile = null;

    /**
     * Inject default magic file to be used on instantiating class.
     *
     * @throws Exception\FileNotFoundException if specified filePath does not exist or is not readable
     */
    public static function setDefaultMagicFile(string $filePath = null): void
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
     * @throws FileNotFoundException if specified filePath does not exist or is not readable
     */
    public function __construct(?string $filePath = null)
    {
        $filePath = $filePath ?: self::$defaultMagicFile ?: (__DIR__ . '/_Data/magic');
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
     */
    public function getMimeType(string $filename, string $default = null): ?string
    {
        $reader = new FileReader($filename);

        $parentOffset = 0;
        $regexp = "/^(?P<Dependant>>?)(?P<Byte>\\d+)\\s+(?P<MatchType"
                . ">\\S+)\\s+(?P<MatchData>\\S+)(?:\\s+(?P<MIMEType>[a-"
                . "z]+\\/[a-z-0-9\.]+)?(?:\\s+(?P<Description>.?+))?)?$/";
        foreach (preg_split('/^/m', $this->magic) as $line) {
            /**
             * @psalm-var array<'Dependant'|'Byte'|'MatchType'|'MatchData'|'MIMEType'|'Description', string|null> $chunks
             */
            $chunks = array();
            if (!preg_match($regexp, $line, $chunks)) {
                continue;
            }

            if ($chunks['Dependant']) {
                $reader->setOffset($parentOffset);
                $reader->skip((int) $chunks['Byte']);
            } else {
                $reader->setOffset($parentOffset = (int) $chunks['Byte']);
            }

            $matchType = strtolower($chunks['MatchType']);
            /** @psalm-var array<string, callable(array<array-key, mixed>):string> $patterns */
            $patterns = [
                "/\\\\ /" => function(): string { return " "; },
                "/\\\\\\\\/" => function(): string { return "\\\\"; },
                "/\\\\([0-7]{1,3})/" => /** @param string[] $match */ function(array $match): string { return pack("H*", base_convert($match[1], 8, 16)); },
                "/\\\\x([0-9A-Fa-f]{1,2})/" => /** @param string[] $match */ function (array $match): string { return pack("H*", $match[1]); },
                "/0x([0-9A-Fa-f]+)/" => /** @param string[] $match */ function (array $match): string { return (string) hexdec($match[1]); },
            ];
            /** @psalm-var string $matchData */
            $matchData = preg_replace_callback_array(
                $patterns,
                $chunks["MatchData"]
            );

            $data = match ($matchType) {
                'byte' => $reader->readInt8(),
                'short' => $reader->readInt16(),
                'long' => $reader->readInt32(),
                'string' => $reader->readString8(strlen($matchData)),
                'date' => $reader->readInt64BE(),
                'beshort' => $reader->readUInt16BE(),
                'belong', 'bedate' => $reader->readUInt32BE(),
                'leshort' => $reader->readUInt16LE(),
                'lelong', 'ledate' => $reader->readUInt32LE(),
                default => null,
            };

            if (strcmp((string) $data, $matchData) === 0) {
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
     * Returns the results of the mime type check.
     */
    public function isMimeType(string $filename, string $mimeType): bool
    {
        return $this->getMimeType($filename) === $mimeType;
    }
}
