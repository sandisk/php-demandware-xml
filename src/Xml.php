<?php
namespace DemandwareXml;

use \DOMDocument;
use \DemandwareXml\XmlException;

class Xml
{
    /**
     * Escapes the value suitable for inclusion in XML and converts booleans to 'true'/'false' strings.
     * Accepts text and unencoded HTML (which will be encoded as UTF-8 entities).
     *
     * @param $value
     * @return string
     */
    public static function escape($value)
    {
        if (is_bool($value)) {
            return ($value ? 'true' : 'false');
        } else {
            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8', false);
        }
    }

    /**
     * Validates the XML file against the specified XSD schema
     * Also formats/normalizes the file to improve human readability and ease diff'ing
     * Formatting will only occur if the XML is valid, otherwise DOMDocument truncates the data making debugging impossible.
     *
     * @param $filePath
     * @param $schemaPath
     * @throws XmlException
     */
    public static function validate($filePath)
    {
        if (! file_exists($filePath)) {
            throw new XmlException('XML file missing');
        } elseif (! is_readable($filePath)) {
            throw new XmlException('XML file is not readable');
        }

        // domdocument dies silently when given a big (1.7GB) file, though known to cope with 892Mb
        // @todo: look at using xmlreader instead @see: https://gist.github.com/tentacode/5934634 for some examples
        if (filesize($filePath) > 1000000000) { // 1Gb
            return;
        }

        libxml_use_internal_errors(true);

        set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) {
            throw new XmlException($errstr, $errno);
        });

        // possibly more efficient to pass a $dom object rather than save/reload, but cleaner to assume already saved
        $dom                     = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput       = true;
        $dom->load($filePath);
        $dom->normalizeDocument();

        restore_error_handler();

        if (! $dom->schemaValidate(realpath(__DIR__ . '/../xsd/catalog.xsd'))) {
            throw new XmlException(static::errorSummary());
        }

        return $dom->save($filePath) > 0;
    }

    /**
     * Summarises `libxml_get_errors()`, grouping the line numbers each unique error occurred on
     *
     * @return string
     */
    private static function errorSummary()
    {
        $errors  = libxml_get_errors();
        $concise = [];
        $summary = '';

        foreach ($errors as $error) {
            $concise[$error->message][] = $error->line;
        }

        foreach ($concise as $error => $lines) {
            $summary .= trim($error) . PHP_EOL . "\tLines: " . implode(', ', $lines) . PHP_EOL . PHP_EOL;
        }

        return trim($summary);
    }
}
