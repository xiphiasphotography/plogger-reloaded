<?php
/** Native, read-only metadata adapter shared by image ingestion and future batch readers. */

function plogger_metadata_text(mixed $value): string {
    if (!is_scalar($value)) return '';
    $text = substr((string) $value, 0, 65536);
    $text = str_replace("\0", '', $text);
    if (!mb_check_encoding($text, 'UTF-8')) $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    return trim($text);
}

function plogger_metadata_number(mixed $value): ?float {
    if (is_string($value) && preg_match('~^(-?\d+)/(\d+)$~D', $value, $parts)) {
        if ((float) $parts[2] == 0.0) return null;
        $number = (float) $parts[1] / (float) $parts[2];
    } elseif (is_numeric($value)) $number = (float) $value;
    else return null;
    return is_finite($number) ? $number : null;
}

/** Read standard JPEG APP1 XMP only: no sidecars, extended packets or image-data scanning. */
function plogger_metadata_jpeg_xmp(string $path): string {
    $stream = @fopen($path, 'rb');
    if (!$stream) return '';
    try {
        if (fread($stream, 2) !== "\xff\xd8") return '';
        for ($count = 0; $count < 128 && ftell($stream) < 2097152; ++$count) {
            if (fread($stream, 1) !== "\xff") break;
            do { $marker = fread($stream, 1); } while ($marker === "\xff");
            if ($marker === '' || in_array(ord($marker), [0xda, 0xd9], true)) break;
            if (ord($marker) === 0x01 || (ord($marker) >= 0xd0 && ord($marker) <= 0xd7)) continue;
            $bytes = fread($stream, 2);
            if (strlen($bytes) !== 2) break;
            $length = ord($bytes[0]) * 256 + ord($bytes[1]) - 2;
            if ($length < 0) break;
            $payload = $length ? fread($stream, $length) : '';
            if (strlen($payload) !== $length) break;
            $prefix = "http://ns.adobe.com/xap/1.0/\0";
            if ($marker === "\xe1" && str_starts_with($payload, $prefix)) return substr($payload, strlen($prefix));
        }
    } finally { fclose($stream); }
    return '';
}

/** @return array<string, string> Bounded UTF-8 XML; DTDs/entities and external resolution are forbidden. */
function plogger_metadata_xmp(string $xml): array {
    if ($xml === '' || strlen($xml) > 65536 || !class_exists('DOMDocument')
        || str_contains($xml, "\0") || !mb_check_encoding($xml, 'UTF-8')
        || preg_match('/<!\s*(?:DOCTYPE|ENTITY)/i', $xml)) return [];
    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument();
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        if (!$document->loadXML($xml, LIBXML_NONET) || $document->doctype !== null) return [];
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
        $xpath->registerNamespace('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');
        $result = [];
        foreach (['title', 'description'] as $field) {
            $nodes = $xpath->query('//dc:'.$field.' | //@dc:'.$field);
            $node = $nodes === false ? null : $nodes->item(0);
            if (!$node) continue;
            $items = $xpath->query('./rdf:Alt/rdf:li | ./rdf:Seq/rdf:li | ./rdf:Bag/rdf:li', $node);
            $value = $node->textContent;
            if ($items !== false && $items->length > 0) {
                $value = $items->item(0)->textContent;
                foreach ($items as $item) {
                    if ($item instanceof DOMElement && $item->getAttributeNS('http://www.w3.org/XML/1998/namespace', 'lang') === 'x-default') {
                        $value = $item->textContent;
                        break;
                    }
                }
            }
            $result['dc:'.$field] = plogger_metadata_text($value);
        }
        return $result;
    } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
}

/** Missing (null) fields use metadata; submitted strings, including empty strings, are authoritative.
 * @param array<string, mixed> $metadata
 * @param array<string, list<string>>|null $priorities
 * @return array<string, string>
 */
function plogger_metadata_fields(array $metadata, ?string $caption = null, ?string $description = null, ?array $priorities = null): array {
    $defaults = ['caption'=>['IPTC.2#005', 'IPTC.2#105', 'XMP.dc:title', 'IFD0.ImageDescription'],
        'description'=>['IPTC.2#120', 'XMP.dc:description', 'IFD0.ImageDescription']];
    $priorities ??= defined('PLOGGER_METADATA_PRIORITIES') ? PLOGGER_METADATA_PRIORITIES : [];
    if (!is_array($priorities)) throw new InvalidArgumentException('Metadata priorities must be an array.');
    $allowed = array_unique(array_merge(...array_values($defaults)));
    foreach ($priorities as $field => $sources) {
        if (!isset($defaults[$field]) || !is_array($sources) || count($sources) > count($allowed)) {
            throw new InvalidArgumentException('Invalid metadata field priorities.');
        }
        foreach ($sources as $source) if (!in_array($source, $allowed, true)) throw new InvalidArgumentException('Unknown metadata source.');
    }
    $result = [];
    foreach (['caption'=>$caption, 'description'=>$description] as $field => $manual) {
        $result[$field] = $manual ?? '';
        $result[$field.'_source'] = $manual === null ? '' : 'manual';
        if ($manual !== null) continue;
        foreach ($priorities[$field] ?? $defaults[$field] as $source) {
            [$section, $key] = explode('.', $source, 2);
            $sectionData = $metadata[$section] ?? [];
            $value = is_array($sectionData) ? ($sectionData[$key] ?? '') : '';
            if (is_array($value)) {
                $values = $value; $value = '';
                foreach ($values as $item) {
                    $value = plogger_metadata_text($item);
                    if ($value !== '') break;
                }
            }
            $value = plogger_metadata_text($value);
            if ($value === '') continue;
            $result[$field] = $value;
            $result[$field.'_source'] = $source;
            break;
        }
    }
    return $result;
}

/** @return array<string, mixed> Legacy sections plus separate IPTC data and diagnostic warnings. */
function plogger_read_metadata(string $path): array {
    if (!function_exists('exif_read_data')) throw new RuntimeException('Enable PHP ext-exif for image metadata.');
    $result = ['IFD0' => [], 'SubIFD' => [], 'GPS' => [], 'IPTC' => [], 'XMP' => [], 'dimensions' => [], 'Errors' => 0, 'warnings' => []];
    if (!is_file($path) || !is_readable($path) || filesize($path) > 33554432) {
        $result['Errors'] = 1;
        $result['warnings'][] = 'Metadata source is unreadable or exceeds 32 MiB.';
        return $result;
    }
    $type = @exif_imagetype($path);
    $info = [];
    $dimensions = @getimagesize($path, $info);
    if ($dimensions === false) {
        $result['Errors'] = 1;
        $result['warnings'][] = 'Invalid image metadata.';
        return $result;
    }
    $result['dimensions'] = ['width'=>$dimensions[0], 'height'=>$dimensions[1]];
    if ($type === IMAGETYPE_JPEG) {
        $packet = plogger_metadata_jpeg_xmp($path);
        if ($packet !== '') {
            $result['XMP'] = plogger_metadata_xmp($packet);
            if ($result['XMP'] === []) $result['warnings'][] = 'Some XMP metadata could not be read.';
        }
    }
    if (isset($info['APP13']) && strlen($info['APP13']) <= 1048576) {
        $iptc = @iptcparse($info['APP13']);
        if (is_array($iptc)) foreach ($iptc as $key => $values) {
            $result['IPTC'][$key] = array_map('plogger_metadata_text', array_slice($values, 0, 100));
        }
    }
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM], true)) return $result;
    // Native parser warnings must not leak filenames or malformed personal metadata into HTML.
    set_error_handler(static function () use (&$result): bool {
        $result['warnings'] = ['Some EXIF metadata could not be read.'];
        return true;
    });
    try { $native = exif_read_data($path, 'IFD0,EXIF,GPS', true, false); }
    catch (Throwable $error) { $native = false; $result['warnings'] = ['Some EXIF metadata could not be read.']; }
    finally { restore_error_handler(); }
    if (!is_array($native)) return $result;
    foreach (['IFD0' => 'IFD0', 'EXIF' => 'SubIFD', 'GPS' => 'GPS'] as $source => $target) {
        foreach (array_slice($native[$source] ?? [], 0, 256, true) as $key => $value) {
            $result[$target][$key] = is_array($value)
                ? array_map('plogger_metadata_text', array_slice($value, 0, 64)) : plogger_metadata_text($value);
        }
    }
    $sub = &$result['SubIFD'];
    foreach (['Make', 'Model', 'ImageDescription', 'Copyright', 'Orientation'] as $key) {
        if (isset($result['IFD0'][$key]) && is_array($result['IFD0'][$key])) $result['IFD0'][$key] = '';
    }
    foreach (['DateTimeOriginal', 'ExposureTime', 'FNumber', 'FocalLength', 'Flash', 'Copyright'] as $key) {
        if (isset($sub[$key]) && is_array($sub[$key])) $sub[$key] = '';
    }
    if (isset($sub['ISOSpeedRatings']) && is_array($sub['ISOSpeedRatings'])) {
        $sub['ISOSpeedRatings'] = $sub['ISOSpeedRatings'][0] ?? '';
    }
    if (isset($sub['UndefinedTag:0xA434'])) $sub['LensModel'] = $sub['UndefinedTag:0xA434'];
    foreach (['FNumber' => ['f/', ''], 'FocalLength' => ['', ' mm']] as $key => [$prefix, $suffix]) {
        if (isset($sub[$key])) {
            $number = plogger_metadata_number($sub[$key]);
            $sub[$key] = $number === null ? '' : $prefix.round($number, 2).$suffix;
        }
    }
    if (isset($sub['ExposureTime'])) {
        $seconds = plogger_metadata_number($sub['ExposureTime']);
        $sub['ExposureTime'] = '';
        if ($seconds !== null && $seconds > 0) {
            if ($seconds >= 1) $sub['ExposureTime'] = round($seconds, 2).' sec';
            else {
                for ($numerator = 1; $numerator < 100; ++$numerator) {
                    $denominator = round($numerator / $seconds);
                    if (abs($denominator - $numerator / $seconds) < 0.02) break;
                }
                $sub['ExposureTime'] = $numerator.'/'.$denominator.' sec';
            }
        }
    }
    if (isset($sub['Flash'])) {
        $flash = (int) $sub['Flash'];
        $labels = [0=>'No Flash',16=>'No Flash',24=>'No Flash',32=>'No Flash',64=>'No Flash',80=>'No Flash',
            1=>'Flash',5=>'Flash, strobe return light not detected',7=>'Flash, strobe return light detected',
            9=>'Compulsory Flash',13=>'Compulsory Flash, Return light not detected',15=>'Compulsory Flash, Return light detected',
            25=>'Flash, Auto-Mode',29=>'Flash, Auto-Mode, Return light not detected',31=>'Flash, Auto-Mode, Return light detected',
            65=>'Red Eye',69=>'Red Eye, Return light not detected',71=>'Red Eye, Return light detected',73=>'Red Eye, Compulsory Flash',
            77=>'Red Eye, Compulsory Flash, Return light not detected',79=>'Red Eye, Compulsory Flash, Return light detected',
            89=>'Red Eye, Auto-Mode',93=>'Red Eye, Auto-Mode, Return light not detected',95=>'Red Eye, Auto-Mode, Return light detected'];
        $sub['Flash'] = $labels[$flash] ?? 'Unknown: '.$flash;
        if (function_exists('plog_tr')) $sub['Flash'] = plog_tr($sub['Flash']);
    }
    if (isset($result['IFD0']['Copyright'])) $sub['Copyright'] = $result['IFD0']['Copyright'];
    // Invalid dates cannot be sent to either database's timestamp column.
    if (isset($sub['DateTimeOriginal'])) {
        $date = DateTimeImmutable::createFromFormat('!Y:m:d H:i:s', $sub['DateTimeOriginal']);
        if (!$date || $date->format('Y:m:d H:i:s') !== $sub['DateTimeOriginal']) $sub['DateTimeOriginal'] = '';
    }
    return $result;
}
