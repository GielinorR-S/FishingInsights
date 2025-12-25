<?php
/**
 * Input Validation
 * PHP 7.3.33 compatible
 */

class Validator {
    /**
     * Validate latitude
     * @param mixed $lat
     * @return float|false Valid latitude or false
     */
    public static function validateLat($lat) {
        $lat = filter_var($lat, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lat < -90 || $lat > 90) {
            return false;
        }
        return $lat;
    }

    /**
     * Validate longitude
     * @param mixed $lng
     * @return float|false Valid longitude or false
     */
    public static function validateLng($lng) {
        $lng = filter_var($lng, FILTER_VALIDATE_FLOAT);
        if ($lng === false || $lng < -180 || $lng > 180) {
            return false;
        }
        return $lng;
    }

    /**
     * Validate latitude and longitude
     * @param mixed $lat
     * @param mixed $lng
     * @return array|false ['lat' => float, 'lng' => float] or false
     */
    public static function validateLatLng($lat, $lng) {
        $lat = self::validateLat($lat);
        $lng = self::validateLng($lng);
        if ($lat === false || $lng === false) {
            return false;
        }
        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Validate days parameter
     * @param mixed $days
     * @return int|false Valid days (1-14) or false
     */
    public static function validateDays($days) {
        $days = filter_var($days, FILTER_VALIDATE_INT);
        if ($days === false || $days < 1 || $days > 14) {
            return false;
        }
        return $days;
    }

    /**
     * Validate date parameter (ISO 8601 YYYY-MM-DD)
     * Allows today, disallows past dates unless DEV_MODE
     * @param mixed $date
     * @return string|false Valid date string or false
     */
    public static function validateDate($date) {
        if (!is_string($date)) {
            return false;
        }
        
        // Check format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            return false;
        }

        $today = new DateTime('today');
        $dateOnly = $dateObj->format('Y-m-d');
        $todayOnly = $today->format('Y-m-d');

        // Allow today, disallow past dates unless DEV_MODE
        if (!$dateObj || $dateOnly < $todayOnly) {
            $devMode = defined('DEV_MODE') ? DEV_MODE : false;
            if (!$devMode) {
                return false;
            }
        }

        return $date;
    }

    /**
     * Sanitize string for output
     * @param string $str
     * @return string
     */
    public static function sanitizeString($str) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

