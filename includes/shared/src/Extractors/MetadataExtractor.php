<?php

declare(strict_types=1);

namespace mvbplugins\Extractors;

use mvbplugins\Abstracts\AbstractMetadataExtractor;
use mvbplugins\Extractors\AvifExifLocator;
use mvbplugins\Extractors\XmpExtractor;
require_once __DIR__ . '/../extractMetadata.php';
// TODO: add the autoload here and make it work and test it. or work with spl_autoload_register?
// TODO: use this https://github.com/magicsunday/imagemeta/tree/main after migration to PHP>= 8.4.0

final class MetadataExtractor extends AbstractMetadataExtractor
{
    // ---- INTERFACE
    public function getMetadata(string $file, $filter=null): array
    {
        // check if file is supported by this extractor
        if (!$this->isFileSupported($file)) {
            return [];
        }

        // check if file exists
        if (!file_exists($file)) {
            return [];
        }
        $meta = [];

        // get file extension and mime type
        $meta['ext'] = $this->getFileExtension($file);
        $mime_type = $this->getMimeType($file);
        if ($mime_type !== null) {
            $meta['mime'] = $mime_type;
        }
        
        // metadata extraction (XMP > IPTC > EXIF) with swith case for extensions 
        switch ($meta['ext']) {
            case 'jpg':
            case 'jpeg':
                $meta = [...$meta, ...$this->getAllTypeMetadata($file, 'Jpeg')];
                break;
            case 'webp':
                $meta = [...$meta, ...$this->getAllTypeMetadata($file, 'Webp')];
                break;
            case 'avif':
                $meta = [...$meta, ...$this->getAllTypeMetadata($file, 'Avif')];
                break;
            default:
                break;
        }

        // filter the metadata
        if ($filter !== null && \is_string($filter)) {
            $meta = $this->filterMetadata($meta, $filter);
        }

        return $meta;
    }
    // ------------- FILE TYPES: 1. Level of metadata extraction for different file types
    private function getAllTypeMetadata(string $file, $type=null): array
    {
        if ($type === null) {
            return [];
        }
        
        // extract the XMP data from the jpeg file which is different for jpg, avif and webp
        $method = 'get' . $type . 'Xmp';
        $xmp = $this->$method($file);
        $meta = [];

        if ($xmp !== null) {
            // parse the XMP data to metadata array which should be on common function
            $xmpExtractor = new XmpExtractor();
            $meta = $xmpExtractor->parseXmpMetadata($xmp);
        }

        // extract the EXIF meta if available. use the same principle as for XMP
        // read the $file as blob 
        $blob = file_get_contents($file);
        if ($blob !== false) {
            $method = 'get' . $type . 'ExifBinary';
            $exifBinary = $this->$method($blob);
            
            if ($exifBinary !== null) {
                // Prüfen, ob Header fehlt:
                if (strncmp($exifBinary, "Exif", 4) !== 0) {
                    $exifBinary = "Exif\0\0" . $exifBinary;
                }

                if (!in_array(substr($exifBinary, 8, 2), ['MM', 'II'], true)) {
                    $exifBinary = preg_replace('/^Exif/', 'Exif45', $exifBinary, 1);
                }

                // In PEL einlesen:
                /*
                $data = new PelDataWindow($exifBinary);
                $exif = new PelExif();
                $exif->load($data);
                $tiff = $exif->getTiff();
                $ifd0 = $tiff->getIfd();
                $result = $this->mapIfd0ToArray($ifd0);
                */
                $result = \mvbplugins\helpers\get_exif_meta($exifBinary);
                // copy exposure_time to shutter_speed if existing
                if (isset($result['exposure_time'])) {
                    $result['shutter_speed'] = $result['exposure_time'];
                }
                // add lens to camera field if existing. Deviates from WordPress wp_read_image_metadata() but we think it's better to have it in one field.
                if (isset($result['lens'])) {
                    $result['camera'] = trim(($result['camera'] ?? '') . ' ' . $result['lens']);
                }

                if ( \is_array($result)) {
                    $meta = [...$result, ...$meta];
                }
            }
        }

        return $meta;
    }

    /**
     * Filter the raw metadata for WordPress to have in the same format and types as wp_read_image_metadata($file).
     * @param array $meta : see MetadataExtractorInterface::getMetadata() for the raw metadata format
     * @param string $filter : the filter to apply, currently only 'wp' or 'wordpress' is supported for WordPress metadata format. Return the unfiltered meta otherwise.
     * 
     * @return array{
     *      aperture: float, // like 4.5 or 2.8
     *      camera: string, 
     *      caption: string, 
     *      copyright: string, 
     *      created_timestamp: int, // unix timestamp, like 1680000000.
     *      credit: string, 
     *      focal_length: float, // like 35.0 or 50.0
     *      iso: int, 
     *      keywords: array, 
     *      orientation: int, 
     *      shutter_speed: float, // like 0.005 or 0.01
     *      title: string
     *      }
     */
    private function filterMetadata(array $meta, $filter): array
    {
        // filter according to the WordPress metadata keys if the filter is 'wp' or 'wordpress'.
        // note : IPTC is not supported and skipped therefore
        // done according to https://developer.wordpress.org/reference/functions/wp_read_image_metadata/
        if ( \in_array(strtolower($filter), ['wp', 'wordpress'])) {
            $wp_supported_meta = [
                'aperture'          => 0,  // (string) Set to EXIF FNumber field.
                'credit'            => '', // (string) Set to IPTC Credit -> IPTC Creator -> EXIF Artist -> EXIF Author 
                'camera'            => '', // (string) Set to the EXIF Model field.
                'caption'           => '', //(string) Set to a non-empty value of one fields EXIF UserComment, EXIF ImageDescription or EXIF Comments. The first non-empty field is used in that order. EXIF ImageDescription is only used if it is less than 80 characters and if the EXIF UserComment field is only used if the EXIF ImageDescription field is empty.
                'created_timestamp' => 0,  // (int) EXIF field DateTimeDigitized as unix timestamp
                'copyright'         => '', // (string) Set to EXIF Copyright field.
                'focal_length'      => 0,  // (string) Set to the EXIF FocalLength field.
                'iso'               => 0,  // (string) Set to the EXIF ISOSpeedRatings field.
                'shutter_speed'     => 0,  // (string) Set to the EXIF ExposureTime field.
                'title'             => '', // (string) see below
                'orientation'       => 0,  // (int) Set to the EXIF Orientation field.
                'keywords'          => [], // (array) missing WP documentation. We Use XMP-Keywords.
        ];

            $filtered_meta = array_intersect_key($meta, $wp_supported_meta);

            // Feld 'credit' befüllen: XMP Creator ->EXIF Artist -> EXIF Author
            if (empty($filtered_meta['credit'])) {
                if (!empty($meta['creator'])) {
                    $filtered_meta['credit'] = $meta['creator'];
                } elseif (!empty($meta['artist'])) {
                    $filtered_meta['credit'] = $meta['artist'];
                } elseif (!empty($meta['author'])) {
                    $filtered_meta['credit'] = $meta['author'];
                }
            }
            // Feld 'camera' befüllen: EXIF Model
            if (empty($filtered_meta['camera'])) {
                if (!empty($meta['model'])) {
                    $filtered_meta['camera'] = $meta['model'];
                }
                // we deviate here and append the lens_model to the camera field
                if (!empty($meta['lens_model'])) {
                    $filtered_meta['camera'] .= ' ' . $meta['lens_model'];
                }
            }
            // Feld 'title' befüllen: 
            // EXIF Title field and if empty then EXIF ImageDescription field but only if less than 80 characters
            if (empty($filtered_meta['title'])) {
                if (!empty($meta['title'])) {
                    $filtered_meta['title'] = $meta['title'];
                } elseif (!empty($meta['description']) && strlen($meta['description']) < 80) {
                    $filtered_meta['title'] = $meta['description'];
                }
            }
            // Feld 'caption' befüllen:
            // EXIF UserComment field if [“title”] is unset AND EXIF:ImageDescription is less than 80 characters
            // EXIF ImageDescription field if [“title”] is set OR EXIF:ImageDescription is more than 80 characters
            // EXIF Comments field if [“title”] does not equal EXIF:Comments
            if (empty($filtered_meta['caption'])) {
                if (!empty($meta['user_comment']) && empty($filtered_meta['title']) && (!empty($meta['description']) && strlen($meta['description']) < 80)) {
                    $filtered_meta['caption'] = $meta['user_comment'];
                } elseif (!empty($meta['description']) && (!empty($filtered_meta['title']) || strlen($meta['description']) >= 80)) {
                    $filtered_meta['caption'] = $meta['description'];
                } elseif (!empty($meta['comments']) && (!empty($filtered_meta['title']) && $meta['title'] !== $meta['comments'])) {
                    $filtered_meta['caption'] = $meta['comments'];
                }
            }
            // Feld 'created_timestamp' konvertieren von EXIF DateTimeDigitized (string) zu unix timestamp (int)
            if (!empty($meta['datetime_digitized'])) {
                $timestamp = $meta['datetime_digitized'];
                if ($timestamp !== false) {
                    $filtered_meta['created_timestamp'] = $timestamp;
                } else {
                    $filtered_meta['created_timestamp'] = 0;
                }
            }

            return $filtered_meta;
        }

        return $meta;
    }
    
    // ------------- XMP: 2. Level of metadata extraction for different file types for XMP
    private function getWebpXmp(string $filename): ?string
    {
        if (!is_file($filename) || !is_readable($filename)) {
            return null;
        }

        $fh = fopen($filename, 'rb');
        if ($fh === false) {
            return null;
        }

        try {
            $header = fread($fh, 12);
            if ($header === false || strlen($header) !== 12) {
                return null;
            }

            if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
                return null;
            }

            while (!feof($fh)) {
                $chunkHeader = fread($fh, 8);
                if ($chunkHeader === false || strlen($chunkHeader) !== 8) {
                    break;
                }

                $fourCC = substr($chunkHeader, 0, 4);
                $size   = unpack('V', substr($chunkHeader, 4, 4))[1];

                if ($fourCC === 'XMP ') {
                    $xmp = ($size > 0) ? fread($fh, $size) : '';
                    if ($xmp === false || strlen($xmp) !== $size) {
                        return null;
                    }

                    return trim($xmp);
                }

                $padding = $size % 2;
                fseek($fh, $size + $padding, SEEK_CUR);
            }

            return null;
        } finally {
            fclose($fh);
        }
    }

    private function getAvifXmp(string $filename): ?string
    {
        if (!is_file($filename) || !is_readable($filename)) {
            return null;
        }

        $data = file_get_contents($filename);
        if ($data === false || $data === '') {
            return null;
        }

        return $this->extractXmpPacketFromBinary($data);
    }
    
    private function getJpegXmp(string $file): ?string
    {
        $data = file_get_contents($file);
        if ($data === false) {
            return null;
        }

        // JPEG-XMP-Identifier im APP1-Segment
        $xmpIdentifier = "http://ns.adobe.com/xap/1.0/\x00";
        $idPos = strpos($data, $xmpIdentifier);

        if ($idPos === false) {
            return null; // kein Standard-XMP gefunden
        }

        // Ab Ende des Identifiers beginnt normalerweise der XMP-XML-Text
        $xmlStartSearchOffset = $idPos + strlen($xmpIdentifier);

        $packetStart = strpos($data, '<?xpacket begin=', $xmlStartSearchOffset);
        if ($packetStart === false) {
            // Fallback: manchmal will man wenigstens <x:xmpmeta ...> finden
            $xmpMetaStart = strpos($data, '<x:xmpmeta', $xmlStartSearchOffset);
            if ($xmpMetaStart === false) {
                return null;
            }

            $xmpMetaEnd = strpos($data, '</x:xmpmeta>', $xmpMetaStart);
            if ($xmpMetaEnd === false) {
                return null;
            }

            $xmpMetaEnd += strlen('</x:xmpmeta>');
            return substr($data, $xmpMetaStart, $xmpMetaEnd - $xmpMetaStart);
        }

        // xpacket-Ende robust finden: end="w" oder end='w', auch 'r' möglich
        if (
            !preg_match(
                '/<\?xpacket\s+end\s*=\s*(["\']).*?\1\s*\?>/s',
                $data,
                $matches,
                PREG_OFFSET_CAPTURE,
                $packetStart
            )
        ) {
            return null;
        }

        $endMatchText = $matches[0][0];
        $endMatchPos  = $matches[0][1];
        $packetEnd    = $endMatchPos + strlen($endMatchText);

        return substr($data, $packetStart, $packetEnd - $packetStart);
    }

    private function extractXmpPacketFromBinary(string $data): ?string
    {
        $start = strpos($data, '<x:xmpmeta');
        if ($start !== false) {
            $endTag = '</x:xmpmeta>';
            $end = strpos($data, $endTag, $start);
            if ($end !== false) {
                return trim(substr($data, $start, $end - $start + strlen($endTag)));
            }
        }

        $start = strpos($data, '<?xpacket begin=');
        if ($start !== false) {
            $endTag = '<?xpacket end=';
            $end = strpos($data, $endTag, $start);
            if ($end !== false) {
                $lineEnd = strpos($data, '?>', $end);
                if ($lineEnd !== false) {
                    return trim(substr($data, $start, $lineEnd - $start + 2));
                }
            }
        }

        return null;
    }
    // ------------- EXIF: 2. Level of metadata extraction for different file types for EXIF

    /**
     * Liefert EXIF ab TIFF-Header oder null.
    */
    private function getJpegExifBinary(string $blob): ?string
    {
        $len = strlen($blob);
        if ($len < 4) {
            return null;
        }

        // JPEG SOI
        if ($blob[0] !== "\xFF" || $blob[1] !== "\xD8") {
            return null;
        }

        $offset = 2;

        while ($offset + 4 <= $len) {
            // Suche Marker-Start 0xFF
            if ($blob[$offset] !== "\xFF") {
                return null; // kaputt / kein valider Stream mehr
            }

            // Mehrere 0xFF-Bytes überspringen
            while ($offset < $len && $blob[$offset] === "\xFF") {
                $offset++;
            }
            if ($offset >= $len) {
                return null;
            }

            $marker = ord($blob[$offset]);
            $offset++;

            // Standalone Marker ohne Längenfeld
            if ($marker === 0xD8 || $marker === 0xD9 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                continue;
            }

            if ($offset + 2 > $len) {
                return null;
            }

            $segmentLength = unpack('n', substr($blob, $offset, 2))[1];
            $offset += 2;

            if ($segmentLength < 2) {
                return null;
            }

            $payloadLength = $segmentLength - 2;
            if ($offset + $payloadLength > $len) {
                return null;
            }

            // APP1
            if ($marker === 0xE1 && $payloadLength >= 6) {
                $payload = substr($blob, $offset, $payloadLength);

                if (strncmp($payload, "Exif\x00\x00", 6) === 0) {
                    $exif = substr($payload, 6);
                    return $this->looksLikeTiffHeader($exif) ? $exif : null;
                }
            }

            // Start of Scan: danach ist Entropie-codierter Bilddatenstrom,
            // weitere APP-Segmente sind dort praktisch nicht mehr relevant.
            if ($marker === 0xDA) {
                break;
            }

            $offset += $payloadLength;
        }

        return null;
    }

    /**
     * Liefert EXIF ab TIFF-Header oder null.
     */
    private function getWebpExifBinary(string $blob): ?string
    {
        $len = strlen($blob);
        if ($len < 12) {
            return null;
        }

        if (substr($blob, 0, 4) !== 'RIFF' || substr($blob, 8, 4) !== 'WEBP') {
            return null;
        }

        $offset = 12;

        while ($offset + 8 <= $len) {
            $chunkId = substr($blob, $offset, 4);
            $chunkSize = unpack('V', substr($blob, $offset + 4, 4))[1];
            $offset += 8;

            if ($offset + $chunkSize > $len) {
                return null;
            }

            if ($chunkId === 'EXIF') {
                $payload = substr($blob, $offset, $chunkSize);
                return $this->looksLikeTiffHeader($payload) ? $payload : null;
            }

            // RIFF/WebP: ungerade Chunkgrößen werden auf gerade Grenze gepaddet.
            $offset += $chunkSize + ($chunkSize & 1);
        }

        return null;
    }

    private function getAvifExifBinary(string $blob): ?string
    {
        $exifBinary = (new AvifExifLocator())->locate($blob);
        return $exifBinary;
    }
    // ------------- HILFSFUNKTIONEN für EXIF-Parsing: TIFF-Header erkennen, IFD0 in Array mappen, Rational-Werte normalisieren, GPS-Koordinaten umrechnen --------------
    private function looksLikeTiffHeader(string $data): bool
    {
        return strlen($data) >= 8
            && (
                strncmp($data, "II\x2A\x00", 4) === 0 ||
                strncmp($data, "MM\x00\x2A", 4) === 0
            );
    }
    /*
    private function mapIfd0ToArray($ifd0): array
    {
        $result = [];
        
        $exifIfd = $ifd0->getSubIfd(PelIfd::EXIF);
        $gpsIfd  = $ifd0->getSubIfd(PelIfd::GPS);        

        // Orientierung
        $result['orientation'] = $this->getValue($ifd0, PelTag::ORIENTATION);

        // EXIF Daten
        $result['iso'] = $this->getValue($exifIfd, PelTag::ISO_SPEED_RATINGS);
        $result['aperture'] = $this->normalizeRational($this->getValue($exifIfd, PelTag::FNUMBER)); //
        //$result['max_aperture'] = $this->normalizeRational($this->getValue($exifIfd, PelTag::MAX_APERTURE_VALUE)); //

        // Belichtungszeit (kann String sein!)
        $exposure = $this->getValue($exifIfd, PelTag::EXPOSURE_TIME);
        $result['shutter_speed'] = $this->normalizeRational($exposure);

        // Datum
        $date = $this->getValue($exifIfd, PelTag::DATE_TIME_ORIGINAL);
        $result['datetime_original'] = $date;
        $result['created_timestamp'] = $date ? strtotime($date) : null;
        // EXIF field DateTimeDigitized
        $dateDigitized = $this->getValue($exifIfd, PelTag::DATE_TIME_DIGITIZED);
        $result['datetime_digitized'] = $dateDigitized;

        // camera and lens info
        $make  = $this->getValue($ifd0, PelTag::MAKE);
        $model = $this->getValue($ifd0, PelTag::MODEL);

        $result['make']  = $make;
        $result['model'] = $model;
        //$result['camera'] = trim($make . ' ' . $model);

        $result['lens_model'] = $this->getValue($exifIfd, 0xA434)?  : null;
        $result['lens_make']  = $this->getValue($exifIfd, 0xA433)?  : null;

        $result['focal_length'] = $this->normalizeRational($this->getValue($exifIfd, PelTag::FOCAL_LENGTH)); //
        $result['focal_length_in_35mm'] = $this->normalizeRational($this->getValue($exifIfd, PelTag::FOCAL_LENGTH_IN_35MM_FILM)); //

        $result['software'] = $this->getValue($ifd0, PelTag::SOFTWARE);
        $result['artist']   = $this->getValue($ifd0, PelTag::ARTIST);
        $result['datetime'] = $this->getValue($ifd0, PelTag::DATE_TIME); // letzter Edit

        $result['exposure_program'] = $this->getValue($exifIfd, PelTag::EXPOSURE_PROGRAM);
        $result['exposure_mode']    = $this->getValue($exifIfd, PelTag::EXPOSURE_MODE);

        $result['metering_mode']    = $this->getValue($exifIfd, PelTag::METERING_MODE);
        $result['light_source']     = $this->getValue($exifIfd, PelTag::LIGHT_SOURCE);

        $result['flash']            = $this->getValue($exifIfd, PelTag::FLASH);

        $result['white_balance'] = $this->getValue($exifIfd, PelTag::WHITE_BALANCE);
        $result['color_space']   = $this->getValue($exifIfd, PelTag::COLOR_SPACE);

        $result['contrast']      = $this->getValue($exifIfd, PelTag::CONTRAST);
        $result['saturation']    = $this->getValue($exifIfd, PelTag::SATURATION);
        $result['sharpness']     = $this->getValue($exifIfd, PelTag::SHARPNESS);
        
        $result['exif_width']  = $this->getValue($exifIfd, PelTag::PIXEL_X_DIMENSION);
        $result['exif_height'] = $this->getValue($exifIfd, PelTag::PIXEL_Y_DIMENSION);

        // EXIF Title field
        $result['title'] = $this->getValue($ifd0, PelTag::XP_TITLE);
        // EXIF ImageDescription field but only if less than 80 characters
        $result['description'] = $this->getValue($ifd0, PelTag::IMAGE_DESCRIPTION);

        // EXIF UserComment field if [“title”] is unset AND EXIF:ImageDescription is less than 80 characters
        $result['user_comment'] = $this->getValue($ifd0, PelTag::USER_COMMENT);
        // EXIF ImageDescription field if [“title”] is set OR EXIF:ImageDescription is more than 80 characters
        // EXIF Comments field if [“title”] does not equal EXIF:Comments
        $result['comments'] = $this->getValue($ifd0, PelTag::XP_COMMENT);

        // EXIF Copyright field
        $result['copyright'] = $this->getValue($ifd0, PelTag::COPYRIGHT);

        // get GPS data if available
        if ($gpsIfd) {
            //$result['gps']['gps_timestamp'] = $this->getValue($gpsIfd, PelTag::GPS_TIME_STAMP);
            //$result['gps']['gps_date']      = $this->getValue($gpsIfd, PelTag::GPS_DATE_STAMP);

            $result['gps']['gps_img_direction'] = $this->normalizeRational($this->getValue($gpsIfd, PelTag::GPS_IMG_DIRECTION));
            //$result['gps']['gps_latitude']  = $this->getGpsCoordinate($gpsIfd, PelTag::GPS_LATITUDE_REF, PelTag::GPS_LATITUDE);
            //$result['gps']['gps_longitude'] = $this->getGpsCoordinate($gpsIfd, PelTag::GPS_LONGITUDE_REF, PelTag::GPS_LONGITUDE);
            $result['gps']['gps_latitude'] = $this->getGpsCoordinate(
                    $gpsIfd,
                    PelTag::GPS_LATITUDE_REF,
                    PelTag::GPS_LATITUDE
                );

                $result['gps']['gps_longitude'] = $this->getGpsCoordinate(
                    $gpsIfd,
                    PelTag::GPS_LONGITUDE_REF,
                    PelTag::GPS_LONGITUDE
                );
            $result['gps']['gps_latitude_ref'] = $this->getValue($gpsIfd, PelTag::GPS_LATITUDE_REF);
            $result['gps']['gps_longitude_ref'] = $this->getValue($gpsIfd, PelTag::GPS_LONGITUDE_REF);
            $result['gps']['gps_altitude']  = $this->normalizeRational($this->getValue($gpsIfd, PelTag::GPS_ALTITUDE)); //
            $result['gps']['gps_altitude_ref'] = $this->getValue($gpsIfd, PelTag::GPS_ALTITUDE_REF);
        }

        return $result;
    }
    */
    private function getValue($ifd, $tag) {
        if (!$ifd) return null;

        $entry = $ifd->getEntry($tag);
        if (!$entry) return null;

        return $entry->getValue();
    }

    private function normalizeRational($value) {
        if ($value === null) return null;

        // Fall A: Array [num, den]
        if (is_array($value) && count($value) === 2) {
            return $value[1] != 0 ? $value[0] / $value[1] : null;
        }

        // Fall B: PelRational Objekt
        if (is_object($value) && method_exists($value, 'getNumerator')) {
            $den = $value->getDenominator();
            return $den != 0 ? $value->getNumerator() / $den : null;
        }

        // Fall C: bereits Zahl
        if (is_numeric($value)) {
            return (float)$value;
        }

        return null;
    }

    private function rationalToFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        // PEL liefert oft [numerator, denominator]
        if (is_array($value) && count($value) === 2 && isset($value[0], $value[1])) {
            $num = (float) $value[0];
            $den = (float) $value[1];

            return $den != 0.0 ? $num / $den : null;
        }

        // Falls PEL schon numerisch liefert
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Fallback für Objekte mit Numerator/Denominator
        if (is_object($value) && method_exists($value, 'getNumerator') && method_exists($value, 'getDenominator')) {
            $num = (float) $value->getNumerator();
            $den = (float) $value->getDenominator();

            return $den != 0.0 ? $num / $den : null;
        }

        return null;
    }

    private function gpsDmsToDecimal(?string $ref, mixed $coord): ?float
    {
        if (!is_array($coord) || count($coord) < 3) {
            return null;
        }

        $deg = $this->rationalToFloat($coord[0]);
        $min = $this->rationalToFloat($coord[1]);
        $sec = $this->rationalToFloat($coord[2]);

        if ($deg === null || $min === null || $sec === null) {
            return null;
        }

        $decimal = $deg + ($min / 60) + ($sec / 3600);

        $ref = strtoupper(trim((string) $ref));

        if ($ref === 'S' || $ref === 'W') {
            $decimal *= -1;
        }
        // round to 6 decimal places to avoid floating point issues and get a more readable output
        $decimal = round($decimal, 6);
        return $decimal;
    }
    /*
    private function getGpsCoordinate(?PelIfd $gpsIfd, int $refTag, int $coordTag): ?float
    {
        $ref   = $this->getValue($gpsIfd, $refTag);
        $coord = $this->getValue($gpsIfd, $coordTag);

        return $this->gpsDmsToDecimal(
            \is_string($ref) ? $ref : null,
            $coord
        );
    }
    */
}