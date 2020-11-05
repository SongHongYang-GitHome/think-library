<?php
/**
 * Desc: Aes加密解密
 * Date: 2020-11-05
 */
namespace library\tools;


class AesCrypt
{
    protected $key;

    /**
     * 构造函数
     * AesCrypt constructor.
     * @param $key
     */
    public function __construct($key = "songhongyang")
    {
        $this->key = $key;
    }

    /**
     * 加密
     * ECB - 128 - addPkcs7Padding
     */
    public function encrypts($plaintext)
    {
        $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $this->key, $options=OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, $this->key, $as_binary=true);
        return base64_encode( $iv.$hmac.$ciphertext_raw);
    }

    /**
     * 解密
     */
    public function decrypts($ciphertext)
    {
        $c = base64_decode($ciphertext);
        $ivlen = openssl_cipher_iv_length($cipher="AES-128-CBC");
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len=32);
        $ciphertext_raw = substr($c, $ivlen+$sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, $this->key, $options=OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, $this->key, $as_binary=true);
        return hash_equals($hmac, $calcmac) ? $original_plaintext : false;
    }
}
