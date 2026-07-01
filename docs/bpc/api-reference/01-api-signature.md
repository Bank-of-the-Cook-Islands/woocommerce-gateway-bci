> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# API request signature

Facing insecure integration, you may be requested to implement an asymmetric request signature. Usually, this requirement is applied only if you carry out P2P/AFT/OCT requests.

To have a possibility to sign requests, you need to perform the following steps:

1. Generate and upload a certificate.
2. Calculate a hash and a signature using your private key and pass the generated hash (X-Hash) and the signature value (X-Signature) in the request header.

These steps are described below in details.

## Generating and uploading a certificate

1. Generate 2048-bit RSA private key. The way of generation depends on privacy policy in your company. For example, you can do it using OpenSSL:
   
   openssl genrsa -des3 -out private.key 2048
2. Generate public CSR (Certificate Signing Request) using the generated private key:
   
   openssl req -key private.key -new -out public.csr
3. Generate a certificate using the generated private key and CSR. The example of generating a certificate for 5 years:
   
   openssl x509 -signkey private.key -in public.csr -req -days 1825 -out public.cer
4. Upload the generated certificate in the Personal Area. To do this, go to Wallet certificates > Merchant API, click Add a certificate and upload the generated public certificate.

## Calculating a hash and a signature

1. Calculate SHA256 hash of the request body as follows:
   
   Use request body as a string (in our example it is amount=10000&password=gcjgcW1&returnUrl=http&userName=signature-api).
   Calculate SHA256 hash from this string, in raw bytes.
   Convert the raw bytes into base64 encoding.
2. Generate a signature for the calculated SHA256 hash with RSA algorythm using the private key.

In our example we use the following private key with the password 12345:

-----BEGIN RSA PRIVATE KEY-----

Proc-Type: 4,ENCRYPTED

DEK-Info: DES-EDE3-CBC,C502560EDE8F82B7

O4+bY1Q1ZcXFLDGVE8s9G2iVISHR/c/IMZKZEjkBED/TbuOCUGVjcav2ZaZO2dO0 lm771N6JNB01uhJbTHScVQ6R0UnGezHFTcsJlAlBa9RQyOwujs4Pk6riOGnLliIs urnTXD0oskBR1wLRA2kp8+V0UPOAMXQaoLxFGE/o8taDGSrkyIcYTBoh9o7ZBxvO SqUWAt2vPbGVyc6XspyuVtgHgEctaJO+E26QTweqdpN5JITF+fDFPNwUrFHoho4N pxpKRWbiCJSpbvbsvhdizkmfgvRw+qYJvTirF3JTfGr14DttudFwjm7sNrr0JILR XPKDUhRyWjkthZM+oDjF2HwISAGkbxcpn4PU7Tywq0uax+5KCQQn2uz4jLM2P6+9 000cvVLwhMnoUdOxuISRXeOcOWVyTO1mPfKiWnHaoO4yS3Y36OCIOe9RHGP8TTmq acb3LUIF30eQyk3KxH/tUB0ScPDKEKMiww13/Kcfr0JkdIe/BWCvV+hSQm38TLQe bTFy+wnD9kHACCwTSVVSOO+rHgJGVIyLgnpClZKWQyyJ4clH7/cORA7mTmp85Ckx IjV5Egu0bPPUMudOB5BnQ4u85RnqXavasgrLRA3JZM4+Jzl8MNy/fsFXnVBQLJJC Wlz/B7S7W8sabRogFuiqkkPmXE/QcpdKQoY3yh748QqMSl8vkA6WgndyYv1EnDDl jA5j7vSf0wKI8BHgdHBEWuEjn3X/s0S/BiPPI6puboYY90tYVJTWSQCR83QrMF3N BIcMu4+RIYu6GWnPx9npZpt0858c670ZII56np24iMse3qgHCOZxsGOenK2x7ta6 163gvaD8bu8xoeQcGVfd6IMbXWVb0+z1hvWR5HWHSalof4lMzZrDsQDKc2UA0ygh hA1+VAl1MAEHVLNCCmyG1SwRwg1PI7FfftW7YARngCZRWkJ1haj1fgy7rtYolrdv lEz/vjFD6diABx67omGgfiJhWdiKIlzsYlX1SW7yaik/Uxf1j8gTFwY34y8ekVd9 6pQTzV2V/4a48ELZl4LvelLWyt1AB3AR+/fM7YG6LYIqlo+qnLtro7Bqu8RNTNRP wcWCd04r/20ulFWMIH8pVa60C98pSdOXriWEI1KDLc0E/fCdhjW2kL+FTPLC7ORe cuzmfI27+06P/BvLZq/FAVBrDAmkioKwe6XYzTjpK1p5jZ3IrNwjAiasY1MNxCRy 5ufhQwkW//d+VUdU5m8Sm30/kXe9UkxMaetXgzPxbB7+5QFFr0bi7D1MjIrJNtTx 5g5E+UfOhqrp8ztBht9csQeFYSYabyyGX4Lh7ymVWrKCVdHlJib3M36nvOjpV/lA zf35sxFz9kaQqNK7xJdQ9Bx6TBUzLjpYhNry37vKk+SIB6Weo+LJ99mALMeX79CB osRqZqX5yrZhaQ8bbpo981nvLy5xFnpRqCuSWVZrVMBq3LQLaOvaCeyGC0V+ZN0C CU6lHlR6XQqd/IjoEN8+8aiVp6Ubw8FuD28TDaEvCltrX3ARL0xFpABsa42LgV1F 09Vi+ju7SSNDvbezN8q0EILq9xp/zNCVhMpyRCIXBq9fzHkyCZ5qMw==

-----END RSA PRIVATE KEY-----

and get the signature: `pJ/gM4PR1/mKGuIxMvTl5pYDDjJslb0BcXFnIxijFn5qKdPd7W+2ueoctziU7omnkYp01/BlracukH1GOPWMSO+9zKuTDdFueFm1utsS0zaPFU+dmc1niGDRWE0CbCXcti/rGSTDPsnR58mwqgVkbCWxKyCDtuo5LxiKPK9mzgWTUuJ8LX6f6u42MURi5tRG6a9dc8l/+J94g0YOk911R6Lqv2jcluEvZ9ZeMMt8hyxowb0eDaCHlussu2CAyqpE9V+EUAc81Jkwv96MMSsA6UnFwEaCV/k+kwYd0jHCx94m2yWX734p9cWsBW7Fr5F0zox9Yck4GOjqe9nJMMB9jQ==` 3. Now you should pass the generated hash (`X-Hash`) and the signature value (`X-Signature`) in the request header. The request will look like this:

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/register.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --header 'X-Hash: eYkMUF+xaYJhsETTIGsctl6DBNZha1ITN8muCcWQtZk=' \
  --header 'X-Signature: pJ/gM4PR1/mKGuIxMvTl5pYDDjJslb0BcXFnIxijFn5qKdPd7W+2ueoctziU7omnkYp01/BlracukH1GOPWMSO+9zKuTDdFueFm1utsS0zaPFU+dmc1niGDRWE0CbCXcti/rGSTDPsnR58mwqgVkbCWxKyCDtuo5LxiKPK9mzgWTUuJ8LX6f6u42MURi5tRG6a9dc8l/+J94g0YOk911R6Lqv2jcluEvZ9ZeMMt8hyxowb0eDaCHlussu2CAyqpE9V+EUAc81Jkwv96MMSsA6UnFwEaCV/k+kwYd0jHCx94m2yWX734p9cWsBW7Fr5F0zox9Yck4GOjqe9nJMMB9jQ==' \
  --data 'amount=10000&password=gcjgcW1&returnUrl=http&userName=signature-api'
```

The request should meet the following requirements:

- All the request parameters are included into the request body (not into the URL).
- If the request parameters are in JSON format, then the following header should be used:
  
  --header 'content-type: application/json'
- If the request parameters are in Query format (like parameterA=valueA&parameterB=valueB), then the following header should be used:
  
  --header 'content-type: application/x-www-form-urlencoded'
- The request contains correct login and password of the API user.
- The X-Hash header contains SHA256 hash of the request body (calculated at step 1).
- The X-Signature header contains the signature for the calculated SHA256 hash with RSA algorythm using the private key (generated at step 2).

Java code example

Below is the Java code example that loads a private key, calculates SHA256 hash, signs it using the private key with the password 12345, and then sends a correct `register.do` request:

```java
import javax.net.ssl.HttpsURLConnection;
import java.io.BufferedReader;
import java.io.DataOutputStream;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.URL;
import java.nio.file.Files;
import java.nio.file.Paths;
import java.security.KeyStore;
import java.security.MessageDigest;
import java.security.PrivateKey;
import java.security.Signature;
import java.util.Base64;

import static java.net.HttpURLConnection.HTTP_OK;

public class SimpleSignatureExample {

    // This example is not production ready. It just shows how to use signatures in API.
    public static void main(String[] args) throws Exception {
        // load private key from jks
        KeyStore ks = KeyStore.getInstance("JKS");
        char[] pwd = "123456".toCharArray();
        ks.load(Files.newInputStream(Paths.get("/path/to/certificates.jks")), pwd);
        PrivateKey privateKey = (PrivateKey) ks.getKey("111111", pwd);

        // Sign
        String httpBody = "amount=10000&password=gcjgcW1&returnUrl=http&userName=signature-api";

        MessageDigest digest = MessageDigest.getInstance("SHA-256");
        Signature signature = Signature.getInstance("SHA256withRSA");
        signature.initSign(privateKey);

        byte[] sha256 = digest.digest(httpBody.getBytes());
        signature.update(sha256);
        byte[] sign = signature.sign();

        // Send
        Base64.Encoder encoder = Base64.getEncoder();
        HttpsURLConnection connection = (HttpsURLConnection) new URL("https://<YOUR_DOMAIN>/payment/rest/register.do").openConnection();
        connection.setDoOutput(true);
        connection.setDoInput(true);
        connection.setRequestMethod("POST");
        connection.addRequestProperty("content-type", "application/x-www-form-urlencoded");
        connection.addRequestProperty("X-Hash", encoder.encodeToString(sha256));
        connection.addRequestProperty("X-Signature", encoder.encodeToString(sign));
        connection.addRequestProperty("Content-Length", String.valueOf(httpBody.getBytes().length));
        try (final DataOutputStream outputStream = new DataOutputStream(connection.getOutputStream())) {
            outputStream.write(httpBody.getBytes());
            outputStream.flush();
        }
        connection.connect();

        InputStream inputStream = connection.getResponseCode() == HTTP_OK ? connection.getInputStream() : connection.getErrorStream();
        BufferedReader reader = new BufferedReader(new InputStreamReader(inputStream));
        String line;
        while ((line = reader.readLine()) != null) {
            System.out.println(line);
        }
    }
}
```

Python code example

Below is the Python code example that generates the signature:

```python
import OpenSSL
from OpenSSL import crypto
import base64
from hashlib import sha256
key_file = open("./priv.pem", "r")
key = key_file.read()
key_file.close()

if key.startswith('-----BEGIN '):
    pkey = crypto.load_privatekey(crypto.FILETYPE_PEM, key)
else:
    pkey = crypto.load_pkcs12(key, password).get_privatekey()

data = “amount=2000&currency=978&userName=test_user&password=test_user_password&returnUrl=https%3A%2F%2Fmybestmerchantreturnurl.com&description=my_first_order&language=en”

sha256_hash = sha256(data.encode()).digest()
base64_hash = base64.b64encode(sha256_hash)
print(base64_hash)

sign = OpenSSL.crypto.sign(pkey, sha256_hash, "sha256")

signed_base64 = base64.b64encode(sign)
print(signed_base64)
```

The private key file for the Python example should have the format:

-----BEGIN PRIVATE KEY-----
MIIEvwIBADANBgkqhkiG9w0BAQEFAASCBKkwggSlAgEAAoIBAQDdpOwhY/p9x0WmBd3HaDfCD+KYung3M8Cxrw0ozF+h//GltRdnkJD7ejsBDB6/YeIVXZeU3AyqWvsi/IfeHwnokGxVg2IMw8OPacY6o1x7W0EQtfRoZa2Cn2PMCpZhEHlIVraXZDDeg4HY26YP0FZxRbpNnpXhGbiop+Bq0wHeE3JIk53cRmwYhxdxMmvFpgNd6C3dYhmnQqLv6WSpVNDFbQxBVU+JDNyR9FQwB1dU2MadgYwFJnEssbhUkM+sXAC4Wv3qhcZek6MWeWsbFIIlyTPa1T3yrWSXIb4qFJEro4pRMmwQ72qG02p8EPx1tlveQo22TojV9WbTPtaVwQtxAgMBAAECggEBANheTGkYOYsZwgMdzPAB7BSU/0bLGdoBuoV6dqUyRdVWjqaOTwe519625uzR0R5RRqxGzlfyLKcM5Aa2cUhEEp8mhatA87G0Va8lue66VOjTH4RZq/tR7v0J7hlc6Ipe05brl5nYo+BEjriNS+I6Jnizcfid7IBvZJW4NFr0G+mWTxl2BhUK/Mk895n8hg9QtgSRoMNO4jK2f0vJrH4hBHehTYpjHx+QhbUyIvsp60bEnNOXzl054TuWBVCYAQHcHTTZowWMY0s1Z0kGNxwsqQm4amW/v+1EqCF4fjRDrU6v/kjDKxGFx9GJUktKZAe2T8e2LySjgGpJO5g4AdxIVpUCgYEA8x9te+i2ijxoS3kIUSwXaPq5EdKGWGl5mW8KZHzmt9LB/CqTKvSOiDkMGoAx/76t5QmKOYojP+Vsc2XdfQfhT6d00MGTdiPBd+8//MmQQ07/D1/PV58Jd1O8bQFU4fZCMpQl/8Azp9ix/NEx0sHDv2KigLfFMBVGeJxwSoU2JzMCgYEA6WJC0BDTA9vx+i+p9i/41f7ozpQuYey5sxdZa2emOSYen6ptxUFLAYXMxVDaBJ89PMUa8GzWoXHhgXzbuRJk74IzUhWgPpneS4HTr5KDStJh2TqWWVLwEIgLwxvtuw0i9uSEU64D/Czzm801lrOhVgmZsWwNpFtP8ujz0v84MssCgYEA1P4YhbB3kx2e5VfwgGSXUcIttr5wMi6deF0+hpCh9DNw/QEzkzNTV2ZbAzCCHSKo5/n2nbg2b3kIDQUWCL6JlqYHAghErwBeMztoHIddmoovjAGM/Z93xJGYhwremWOL1RHTRH7XAlomfG2tL43PdvDrmsbkut44sdujyLVxnt8CgYBirK3tBMADKLJVgmOM+FlwORe7iAFYW9tj8iJXe/pWvVxDS66fsOyCl0ytvHKBc8ZTdE7gilPw7JJYyi6oQDO25EjIkuYusaXALQMQf5TNRMgkLVY2LA/eHXdDpgJMjNBUrOeZ7cA3ldXl8MyQjCBRnTuDPVlDPWw/GulEM65SIwKBgQDIEv8XK2YBkZrr+0fZSFTQAeK4R7Ve3z4hbpHhJi41YanCNaEWoeYAuQd6/b/QLwABllvfJBDYCNnF8heUxqISpyWd+FZ8nhZtxBoKj5l80czTcutIz/M+ETcvl8FqnMBsoCdp1wodqaLkOx6DIldgKLze6AqKXl5lHUsU4mvVqg==
-----END PRIVATE KEY-----

