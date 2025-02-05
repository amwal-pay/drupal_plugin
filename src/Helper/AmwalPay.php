<?php

namespace  Drupal\commerce_amwalpay\Helper;
class AmwalPay
{
    /**
     * Encrypts the input string using SHA-256 with a given hex key.
     *
     * @param string $input The input string to be hashed.
     * @param string $hexKey The hex key used for hashing.
     * @return string The resulting SHA-256 hash.
     */
    public static function encryptWithSHA256($input, $hexKey)
    {
        // Convert the hex key to binary
        $binaryKey = hex2bin($hexKey);
        // Calculate the SHA-256 hash using hash_hmac
        $hash = hash_hmac('sha256', $input, $binaryKey);
        return $hash;
    }

    /**
     * Generates a signed string for payment request.
     *
     * @param float $amount The amount for the transaction.
     * @param int $currencyId The currency ID.
     * @param string $merchantId The merchant ID.
     * @param string $merchantReference The merchant reference.
     * @param string $terminalId The terminal ID.
     * @param string $hmacKey The HMAC key for signing.
     * @param string $trxDateTime The transaction date and time.
     * @return string The generated signed string in uppercase.
     */
    public static function generateString(
        $amount,
        $currencyId,
        $merchantId,
        $merchantReference,
        $terminalId,
        $hmacKey,
        $trxDateTime
    ): string {
        // Create the string to be signed
        $string = "Amount={$amount}&CurrencyId={$currencyId}&MerchantId={$merchantId}&MerchantReference={$merchantReference}&RequestDateTime={$trxDateTime}&SessionToken=&TerminalId={$terminalId}";

        // Generate SIGN
        $sign = self::encryptWithSHA256($string, $hmacKey);
        return strtoupper($sign);
    }

    /**
     * Generates a signed string for filtering data.
     *
     * @param array $data The data to be signed.
     * @param string $hmacKey The HMAC key for signing.
     * @return string The generated signed string in uppercase.
     */
    public static function generateStringForFilter(
        $data,
        $hmacKey
    ) {
        // Convert data array to string key-value pairs
        $string = '';
        foreach ($data as $key => $value) {
            // Sanitize the value to avoid "null" or "undefined" strings
            $string .= $key . '=' . ($value === "null" || $value === "undefined" ? '' : $value) . '&';
        }
        $string = rtrim($string, '&'); // Remove trailing '&'

        // Generate SIGN
        $sign = self::encryptWithSHA256($string, $hmacKey);
        return strtoupper($sign);
    }

    /**
     * Adds logs to a specified file.
     *
     * @param string $debug Debug flag to enable or disable logging.
     * @param string $file The log file path.
     * @param string $note A note to log.
     * @param mixed $data Additional data to log (optional).
     */
    public static function addLogs($debug, $file, $note, $data = false)
    {
        try {
            if (is_bool($data)) {
                // Log a simple note
                if ('1' === $debug) {
                    error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - ' . $note, 3, $file);
                }
            } else {
                // Log note with additional data
                if ('1' === $debug) {
                    error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - ' . $note . ' -- ' . json_encode($data), 3, $file);
                }
            }
        } catch (\Exception $e) {
            // Handle any exceptions that occur during logging
            error_log(PHP_EOL . gmdate('d.m.Y h:i:s') . ' - Error logging: ' . $e->getMessage(), 3, $file);
        }
    }
}