<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class PdfContentNormalizer
{
    private const DEFAULT_IMAGE_WIDTH = '70%';

    public static function normalize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = self::createDocument($html);
        $xpath = new DOMXPath($document);

        self::preserveEmptyParagraphs($xpath);
        self::normalizeImages($document, $xpath);

        return self::getBodyContent($document);
    }

    private static function createDocument(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="pdf-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        return $document;
    }

    private static function preserveEmptyParagraphs(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//p') as $paragraph) {
            if (!$paragraph instanceof DOMElement) {
                continue;
            }

            if (trim($paragraph->textContent) === '' && !self::hasElementChild($paragraph)) {
                $paragraph->appendChild($paragraph->ownerDocument->createTextNode("\xc2\xa0"));
            }
        }
    }

    private static function normalizeImages(DOMDocument $document, DOMXPath $xpath): void
    {
        $images = [];
        foreach ($xpath->query('//img') as $image) {
            if ($image instanceof DOMElement && !self::isInsidePdfImageContainer($image)) {
                $images[] = $image;
            }
        }

        foreach ($images as $image) {
            self::wrapImageForPdf($document, $image);
        }
    }

    private static function wrapImageForPdf(DOMDocument $document, DOMElement $image): void
    {
        $parentStyle = $image->parentNode instanceof DOMElement
            ? $image->parentNode->getAttribute('style')
            : '';

        $style = self::mergeStyles(
            $parentStyle,
            $image->getAttribute('wrapperstyle'),
            $image->getAttribute('containerstyle'),
            $image->getAttribute('style')
        );

        $container = $document->createElement('div');
        $container->setAttribute('class', 'pdf-image-container');
        $container->setAttribute(
            'style',
            sprintf(
                'width: %s; max-width: 100%%; margin: %s;',
                htmlspecialchars(self::resolveImageWidth($style, $image), ENT_QUOTES, 'UTF-8'),
                self::resolveImageMargin($style)
            )
        );

        $image->removeAttribute('wrapperstyle');
        $image->removeAttribute('containerstyle');
        $image->removeAttribute('style');
        $image->removeAttribute('width');
        $image->removeAttribute('height');
        $image->setAttribute('style', 'width:100%; height:auto;');

        $parent = $image->parentNode;
        if (!$parent instanceof DOMNode) {
            return;
        }

        $parent->replaceChild($container, $image);
        $container->appendChild($image);
    }

    /**
     * Editor menyimpan sebagian layout gambar sebagai inline style. Di sini style
     * tersebut diubah menjadi array agar mudah dibaca tanpa memproses HTML mentah.
     */
    private static function mergeStyles(string ...$styleAttributes): array
    {
        $styles = [];

        foreach ($styleAttributes as $styleAttribute) {
            foreach (explode(';', $styleAttribute) as $declaration) {
                $parts = explode(':', $declaration, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                $property = strtolower(trim($parts[0]));
                $value = trim($parts[1]);

                if ($property !== '' && $value !== '') {
                    $styles[$property] = $value;
                }
            }
        }

        return $styles;
    }

    private static function resolveImageWidth(array $style, DOMElement $image): string
    {
        if (!empty($style['width'])) {
            return $style['width'];
        }

        $width = $image->getAttribute('width');
        if ($width === '') {
            return self::DEFAULT_IMAGE_WIDTH;
        }

        return is_numeric($width) ? $width . 'px' : $width;
    }

    private static function resolveImageMargin(array $style): string
    {
        $float = strtolower($style['float'] ?? '');
        $textAlign = strtolower($style['text-align'] ?? '');
        $margin = self::normalizeCssValue($style['margin'] ?? '');
        $marginLeft = self::normalizeCssValue($style['margin-left'] ?? '');
        $marginRight = self::normalizeCssValue($style['margin-right'] ?? '');

        if (
            $float === 'right'
            || $textAlign === 'right'
            || self::isRightAlignedMargin($margin, $marginLeft, $marginRight)
        ) {
            return '0 0 0 auto';
        }

        if (
            $float === 'left'
            || $textAlign === 'left'
            || self::isLeftAlignedMargin($margin, $marginLeft, $marginRight)
        ) {
            return '0 auto 0 0';
        }

        return '0 auto';
    }

    private static function isRightAlignedMargin(string $margin, string $marginLeft, string $marginRight): bool
    {
        if (self::isAuto($marginLeft) && self::isZero($marginRight)) {
            return true;
        }

        $parts = self::splitMargin($margin);
        return count($parts) === 4 && self::isZero($parts[1]) && self::isAuto($parts[3]);
    }

    private static function isLeftAlignedMargin(string $margin, string $marginLeft, string $marginRight): bool
    {
        if (self::isZero($marginLeft) && self::isAuto($marginRight)) {
            return true;
        }

        $parts = self::splitMargin($margin);
        return count($parts) === 4 && self::isAuto($parts[1]) && self::isZero($parts[3]);
    }

    private static function splitMargin(string $margin): array
    {
        if ($margin === '') {
            return [];
        }

        return preg_split('/\\s+/', $margin) ?: [];
    }

    private static function normalizeCssValue(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }

    private static function isAuto(string $value): bool
    {
        return $value === 'auto';
    }

    private static function isZero(string $value): bool
    {
        return in_array($value, ['0', '0px', '0em', '0rem', '0%'], true);
    }

    private static function hasElementChild(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }

    private static function isInsidePdfImageContainer(DOMElement $image): bool
    {
        $parent = $image->parentNode;

        while ($parent instanceof DOMElement) {
            if (str_contains(' ' . $parent->getAttribute('class') . ' ', ' pdf-image-container ')) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }

    private static function getBodyContent(DOMDocument $document): string
    {
        $root = $document->getElementById('pdf-root');
        if (!$root instanceof DOMElement) {
            return '';
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return $html;
    }
}


