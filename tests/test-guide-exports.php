<?php

declare(strict_types=1);

/**
 * The Word and PDF setup guides are generated from docs/merchant-setup-guide.md
 * by scripts/build-docs.php, and their cover is stamped with the plugin version.
 * A release that bumps the version without regenerating ships merchants a guide
 * labelled with the previous release, so pin the stamp to the header here.
 *
 * The zip extension is absent from the stock php:8.3-cli image, so the .docx
 * half of the check skips rather than failing CI for the wrong reason.
 */

namespace {
    define('ABSPATH', __DIR__);

    function sanitize_text_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

namespace BCI\Woo {
    require_once dirname(__DIR__) . '/includes/class-config.php';

    /**
     * Inflates every deflate stream in a PDF and returns them joined.
     */
    function pdf_text(string $file): string
    {
        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException('Could not read ' . basename($file));
        }

        $text = '';
        $offset = 0;

        // Match the newline before "stream" so the search does not land inside
        // the "endstream" keyword and skip the next object.
        while (($start = strpos($raw, "\nstream\n", $offset)) !== false) {
            $start += strlen("\nstream\n");
            $end = strpos($raw, "\nendstream", $start);
            if ($end === false) {
                break;
            }

            $inflated = @gzuncompress(substr($raw, $start, $end - $start));
            if ($inflated !== false) {
                $text .= $inflated;
            }

            $offset = $end + strlen("\nendstream");
        }

        return $text;
    }

    $root = dirname(__DIR__);
    $expected = sprintf('plugin v%s', Config::VERSION);

    $docx = $root . '/docs/Merchant Setup Guide.docx';
    $pdf = $root . '/docs/Merchant Setup Guide.pdf';

    foreach ([$docx, $pdf] as $export) {
        if (!is_file($export)) {
            throw new \RuntimeException(sprintf(
                'Missing %s — run: php scripts/build-docs.php',
                basename($export)
            ));
        }
    }

    if (strpos(pdf_text($pdf), $expected) === false) {
        throw new \RuntimeException(sprintf(
            'The PDF setup guide is not stamped "%s" — run: php scripts/build-docs.php',
            $expected
        ));
    }

    if (class_exists('ZipArchive')) {
        $archive = new \ZipArchive();
        if ($archive->open($docx) !== true) {
            throw new \RuntimeException('The .docx setup guide is not a readable Word package.');
        }

        $document = $archive->getFromName('word/document.xml');
        $archive->close();

        if ($document === false || strpos($document, $expected) === false) {
            throw new \RuntimeException(sprintf(
                'The .docx setup guide is not stamped "%s" — run: php scripts/build-docs.php',
                $expected
            ));
        }
    } else {
        echo "Note: no zip extension, skipped the .docx half of the check.\n";
    }

    echo "Guide export tests passed.\n";
}
