> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Callback notifications

The payment gateway API allows you to receive callback notifications on changes of payment statuses.

### General information

#### Events that can trigger notifications

You can receive notifications about changes in order payment status and other events in the Payment Gateway.

Callback notifications are not notifications by email or phone. Callback notifications are received via the API.

The most common notifications describe changes in order status, such as:

- debiting of funds
- holding of funds
- payment reversal
- refund

More advanced integrations may make use of additional callback triggers like:

- saving of a card (i.e., storing a credential)
- enabling/disabling an existing stored credential
- payments being declined, etc.

The trigger type is passed in the `operation` parameter of the callback (see details below). For convenience, the callbacks for addional triggers can be directed to another URL by using the `dynamicCallbackUrl` parameter in order registration requests.

### Integration with callback

Instead of the last step of the Redirect integration you may choose to use one of the following approaches.

#### Make use of returnUrl

When your web-site code located at ``returnUrl`` (for example, `https://mybestmerchantreturnurl.com/?back&orderId=61c33664-85a0-7d6b-af26-09ee009c4000&lang=en`) identifies a cardholder being redirected back from the gateway after a payment attempt, you can check the order status using the API request ``getOrderStatusExtended``.
This option is the easiest one but it is not completely reliable because the cardholder redirect may fail (for example, as a result of a broken connection or the cardholder closing the browser) and returnUrl may not get the "trigger" to proceed with getOrderStatusExtended.

getOrderStatusExtended.do

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/getOrderStatusExtended.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data orderId=016b6f47-4628-7ea2-80f5-6c6e00a7d8c0 \
  --data language=en
```

#### Make use of a signed gateway callback

If you know how to handle digital certificates and signatures, you can use a digitally signed callback with a checksum that the gateway may be configured to send. A checksum is used for verification and security purposes. After the callback signature has been verified on your side, there is no need to send `getOrderStatusExtended` because the callback includes the order status.

```bash
https://mybestmerchantreturnurl.com/callback/?mdOrder=1234567890-098776-234-522&orderNumber=0987&checksum=DBBE9E54D42072D8CAF32C7F660DEB82086A25C14FD813888E231A99E1220AB3&operation=deposited&status=1
```

### Types of notifications

#### Notifications without checksums

These notifications contain only information about the order, so potentially, the merchant risks accepting a notification sent by an attacker as genuine.

#### Notifications with checksums

These notifications contain an authentication code in addition to order information. The authentication code is a checksum of order data. This checksum allows to make sure that the callback notification is genuine and was sent by the payment gateway.
There are two methods of implementing callback notifications with checksums:

- using symmetric cryptography — same (symmetric) cryptographic key is used by the payment gateway to create a checksum and by a merchant to validate it;
- using asymmetric cryptography — to create a checksum the payment gateway uses its private key known only to the payment gateway, while for validation of the created checksum the corresponding public key is used, this public key can be distributed openly.

### Requirements for SSL certificates on the store’s website

If a callback is delivered over HTTPS connection, the identity of the merchant's website must be verified with an SSL certificate issued and signed by a trusted certificate authority (check the table below). Self-signed certificates are not allowed.

| Requirement | Description |
| --- | --- |
| Signature algorithm. | Not lower than SHA-256. |
| Supported certification authorities. | Below are examples of organizations that register digital certificates:  Thawte Consulting cc; VeriSign; DigiCert Inc; COMODO CA Limited; GeoTrust Inc.; GlobalSign; Trustis Limited; UniTrust; GoDaddy. |

### URL format for callback notifications

POST and GET requests can be sent.

Below is an example for a default GET request, without additional parameters. The parameters are received in the query.

Notification without a checksum (GET)

```bash
https://mybestmerchantreturnurl.com/callback/?mdOrder=
1234567890-098776-234-522&orderNumber=0987&operation=deposited&
callbackCreationDate=Mon Jan 31 21:46:52 UTC 2022&status=0
```

Notification with a checksum (GET)

```bash
https://mybestmerchantreturnurl.com/callback/?mdOrder=1234567890-098776-234-522&
orderNumber=0987&checksum=DBBE9E54D42072D8CAF32C7F660DEB82086A25C14FD813888E231A99E1220AB3&
operation=deposited&callbackCreationDate=Mon Jan 31 21:46:52 UTC 2022&status=0
```

For POST callbacks, you will receive the same parameters in HTTP body (instead of query parameters).

Notification without a checksum (POST)

```bash
https://mybestmerchantreturnurl.com/callback/
mdOrder=
1234567890-098776-234-522&orderNumber=0987&operation=deposited&
callbackCreationDate=Mon Jan 31 21:46:52 UTC 2022&status=0
```

Notification with a checksum (POST)

```bash
https://mybestmerchantreturnurl.com/callback/
mdOrder=1234567890-098776-234-522&
orderNumber=0987&checksum=DBBE9E54D42072D8CAF32C7F660DEB82086A25C14FD813888E231A99E1220AB3&operation=deposited&callbackCreationDate=Mon Jan 31 21:46:52 UTC 2022&status=0
```

The passed parameters are shown in the table below.

The table contains only basic parameters. You can also use additional parameters if they are configured in Payment Gateway.

| Parameter | Description |
| --- | --- |
| `mdOrder` | Unique order number stored in the payment gateway. |
| `orderNumber` | Unique order number (identifier) in merchant's system. |
| `checksum` | Authentication code (checksum) resulting from received parameters. |
| `operation` | Type of event that triggered notification:  approved - funds are put on hold on buyer's account; deposited - order deposited; reversed - payment was reversed; refunded - order was refunded; bindingCreated - payer's card has been saved (a credential was stored); bindingActivityChanged - an existing stored credential was disabled/enabled; declinedByTimeout - payment was declined because it timed out; declinedCardPresent - a declined card-present transaction (payment with physical card). |
| `status` | Indicates if an operation was successfully processed:  1 - success; 0 - fail. |

### Custom headers for callback notifications

You can request the technical support service to set custom headers for callback notifications. For example:

```bash
'http://mybestmerchantreturnurl.com/callback.php', headers={Authorization=token, Content-type=plain
/text}, params={orderNumber=349002, mdOrder=5ffb1899-cd1e-7c1e-8750-e98500093c43, operation=deposited, status=1}
```

where `{Authorization=token, Content-type=plain/text}` is a custom header.

### Examples

### Example of a notification URL without a checksum

```bash
https://mybestmerchantreturnurl.com/callback/?mdOrder=1234567890-098776-234-522&orderNumber=0987&operation=deposited&status=0
```

#### Example of a notification URL with a checksum

```bash
https://mybestmerchantreturnurl.com/callback/?mdOrder=1234567890-098776-234-522&orderNumber=0987&checksum=DBBE9E54D42072D8CAF32C7F660DEB82086A25C14FD813888E231A99E1220AB3&operation=deposited&status=0
```

### Algorithm for processing callback notifications

Sections below contain notification processing algorithms depending on notification type.

#### Notification without a checksum

1. The payment gateway sends to the merchant's server the following request.
   https://mybestmerchantreturnurl.com/callback/?mdOrder=1234567890-098776-234-522&orderNumber=0987&operation=deposited&status=0
2. The merchant's server returns HTTP 200 OK to the payment gateway.

#### Notification with a checksum

1. The payment gateway sends the following HTTPS request to the merchant's server - please, note that:
   
   when using symmetric cryptography, the checksum is generated using a key common for the payment gateway and the merchant;
   when using asymmetric cryptography, the checksum is generated using a private key known only to the payment gateway.
   https://mybestmerchantreturnurl.com/path?amount=123456&orderNumber=10747&checksum=DBBE9E54D42072D8CAF32C7F660DEB82086A25C14FD813888E231A99E1220AB3&mdOrder=3ff6962a-7dcc-4283-ab50-a6d7dd3386fe&operation=deposited&status=1
   The order of the parameters in a notification can be arbitrary.
   Note that callback notifications are sent only via port 443 (HTTPS).
2. On the merchant's side checksum and sign_alias parameters are removed from the notification parameters string, and the value of checksum parameter is saved for verification of the notification's authenticity.
3. The parameters and their values that are left are used for creating the following string.
   parameter_name1;paramenter_value1;parameter_name2;paramenter_value2;…;parameter_nameN;paramenter_valueN;
   In this case pairs name_parameter;value_parameter must be sorted in direct alphabetical order (ascending) by parameter names.
   Here is an example of a generated parameter string
   amount;123456;mdOrder;3ff6962a-7dcc-4283-ab50-a6d7dd3386fe;operation;deposited;orderNumber;10747;status;1;
   
   
   Note that the string ends with a semicolon (";").
4. The checksum is calculated on the merchant's side, the method of calculation depends on the method of its formation:
   
   when using symmetric cryptography - with the help of HMAC-SHA256 algorithm and a private key shared with the payment gateway;
   when using asymmetric cryptography - with the help of a hashing algorithm that depends on how the key pair is created and a public key that is associated with a private key located in the payment gateway.
5. In the resulting checksum string, all lower-case letters are replaced by upper-case letters.
6. The resulting value must be compared with the checksum extracted earlier from checksum parameter.
7. If the checksums match, the server sends an HTTP code 200 OK to the payment gateway.

If the checksums match, this notification is authentic and was sent by the payment gateway. Otherwise, it is likely that the attacker is trying to pass off his notification as a payment gateway notification.

### Payment status notification

In order to detect whether the payment run successfully or not you need to:

1. Check the signature (checksum parameter in callback);
2. Check two callback parameters: operation and status.

If the `operation` value is `approved` or `deposited`, then the callback refers to the payment.

- Approved Successfully → operation = approved & status = 1 (Successful Operation)
- Approval was Declined → operation = approved & status = 0 (Failed Operation)
- Deposited Successfully → operation = deposited & status = 1 (Successful Operation)
- Deposit was Declined → operation = deposited & status = 0 (Failed Operation)

### When notifications fail

If a response other than `200 OK` is returned to the payment gateway, the notification is considered unsuccessful. In this case, the payment gateway repeats the notification at intervals of 30 seconds until one of the following conditions is met:

- the payment gateway receives 200 OK, OR
- there are 3 successive notification failures.

These settings can be configured on merchant level. For details, please contact our Support Team.

When one of the above conditions is met, attempts to send a callback notification about an event stop.

### Additional callback parameters

In callback notifications, you can use the following additional parameters if they are configured in the Payment Gateway. If you need them, contact our support team.

You can configure the sending of particular additional parameters with the help of the Support team. In this case the additional parameter will be added to the list of parameters.

Some parameters need special permissions to be configured in Payment Gateway.

| Parameter | Description | Type of event |
| --- | --- | --- |
| `bindingId` | UUIID of created/updated stored credential. | BINDING_CREATED, BINDING_ACTIVITY_CHANGED |
| `email` | Client's email. | BINDING_CREATED |
| `phone` | Client's phone number. | BINDING_CREATED |
| `panMasked` | Masked PAN of the client's card. | BINDING_CREATED |
| `panCountryCode` | Client's country code. | BINDING_CREATED |
| `enabled` | Whether a store credential is active (true/false). | BINDING_ACTIVITY_CHANGED |
| `currentReverseAmountFormatted` | Formatted amount of the reversal operation. | REVERSED |
| `currentRefundAmountFormatted` | Formatted amount of the refund operation. | REFUNDED |
| `operationRefundedAmountFormatted` | Formatted amount of the refund operation. | REFUNDED |
| `operationRefundedAmount` | Refund amount in minor currency units (e.g. in cents etc.). | REFUNDED |
| `externalRefundId` | External identifier of the refund operation. | REFUNDED |
| `callbackCreationDate` | Callback notification creation date. Special merchant setting is required. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT, BINDING_CREATED, BINDING_ACTIVITY_CHANGED |
| `status` | Operation status: 1 - success, 0 - failure | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `operation` | Callback type. Possible values: deposited, approved, reversed, refunded, bindingCreated, bindingActivityChanged, declinedByTimeout, declinedCardpresent | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT, BINDING_CREATED, BINDING_ACTIVITY_CHANGED |
| `finishCheckUrl` | URL for receipt generation | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `sign_alias` | Name of the key used for signature. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT, BINDING_CREATED, BINDING_ACTIVITY_CHANGED |
| `checksum` | Callback shecksum (used for callbacks with checksum). | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT, BINDING_CREATED, BINDING_ACTIVITY_CHANGED |
| `cardholderName` | Cardholder name. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `amount` | Registered order amount in minor currency units. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `paymentAmount` | Registered order amount in minor currency units. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `amountFormatted` | Formatted registered order amount. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `feeAmount` | Fee amount in minor currency units. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `approvedAmount` | Preauthorized amount in minor currency units. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `depositedAmount` | Deposited amount in minor currency units. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `refundedAmount` | Refund amount in minor currency units. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `approvedAmountFormatted` | Formatted preauthorized amount. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `depositedAmountFormatted` | Formatted deposited amount. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `refundedAmountFormatted` | Formatted refunded amount. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `totalAmountFormatted` | Formatted total order amount (registered amount + fee). | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `depositedTotalAmountFormatted` | Formatted total deposited amount (all deposited amounts + all refunded amounts + fee). | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `approvalCode` | Payment authorization code received from processing. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `authCode` | Authorization code. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `bankName` | Name of the bank that issued the client's card. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `currency` | Order currency. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `depositFlag` | The flag that specifies the type of the operation.  1 - purchase 2 - preauthorization | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `eci` | Electronic commerce indicator. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `ip` | Client's IP address. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `ipCountryCode` | Country code of the client's IP address. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `maskedPan` | Masked number of the client's card. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `mdOrder` | Order number in the payment gateway. Unique within the payment gateway. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `mdorder` | Order number in the payment gateway. Unique within the payment gateway. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `merchantFullName` | Merchant's full name. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `merchantLogin` | Merchant's login. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `orderDescription` | Order description. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `orderNumber` | Order number (ID) in the merchant's system. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `threeDSType` | Type of transaction in terms of 3 DS. Possible values: SSL, THREE_DS1_FULL, THREE_DS1_ATTEMPT, THREE_DS2_FULL, THREE_DS2_FRICTIONLESS, THREE_DS2_ATTEMPT, THREE_DS2_EXEMPTION_GRANTED, THREE_DS2_3RI, THREE_DS2_3RI_ATTEMPT | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `date` | Date of the order creation. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `clientId` | Customer number (ID) in the merchant's system. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT,BINDING_CREATED, BINDING_ACTIVITY_CHANGED |
| `actionCode` | Code of the operation execution result. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `actionCodeDescription` | Description of the code of the operation execution result. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `paymentRefNum` | Reference Retrieval Number - transaction ID assigned by Acquiring Bank. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `paymentState` | Order status. Possible values: started, payment_approved, payment_declined, payment_void, payment_deposited, refunded, pending, partly_deposited | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `paymentWay` | Order payment way. Find more possible values of the parameter here. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `processingId` | Identifier of the customer in processing. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `refNum` | Reference Retrieval Number - transaction ID assigned by Acquiring Bank. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `refnum` | Reference Retrieval Number - transaction ID assigned by Acquiring Bank. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `terminalId` | Terminal identifier in the system that processes the payment. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `paymentSystem` | Payment system name. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `currencyName` | ISO 3-Letter Currency Code. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `transactionAttributes` | Order attributes. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `paymentDate` | Order payment date. | DEPOSITED, APPROVED, REVERSED, REFUNDED |
| `depositedDate` | Date of order deposit operation. | DEPOSITED, APPROVED, REVERSED, REFUNDED |
| `refundedDate` | Date of order refund operation. | REFUNDED |
| `reversedDate` | Date of order reversal operation. | DEPOSITED, REVERSED, REFUNDED |
| `declineDate` | Date of order cancellation. | DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `xid` | Electronic commerce indicator of the transaction defined by the merchant. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `cavv` | Cardholder authentication value. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `authValue` | Cardholder authentication value. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `sessionExpiredDate` | Date and time of the order expiration. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `tokenizeCryptogram` | Tokenized cryptogram. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |
| `creditBankName` | Name of the bank that issued the card to be credited (in P2P). | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
| `creditPanCountryCode` | Country code of recipient card (in P2P). | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
| `isInternationalP2P` | Whether P2P transfer is international. | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
| `recipientData` | Information about P2P recipient. | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
| `transactionTypeIndicator` | Information about P2P recipient. Possible values:  A - Account to Account (one person) B - Transfer for the purpose of purchasing cryptocurrency C - Transfer for the purpose of purchasing cryptocurrency D - Funds Disbursement E - Account to Account (one person) F - Transfer for gambling betting G - Online gambling payout L - Card Bill Payment O - Loan payment P - Person to Person (defferent persons) W - Transfer to own account of a staged digital wallet for payment. | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
|  |  |  |
| `operationType` | Type of P2P operation: AFT/OCT. | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
| `debitBankName` | Name of the bank that issued the card to be debited (in P2P). | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
| `debitPanCountryCode` | Country code of the card tp be debited (in P2P). | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
| `p2pDebitRrn` | RRN (Reference Retrieval Number) of P2P debit operation. | DEPOSITED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT |
| `avsCode` | A code of the AVS verification response (checking the address and postal code of the cardholder). Possible values:  -1 – postal code and address are the same. 1 – address matches, postal code doesn't match. 2 - postal code matches, address doesn't match. 3 - postal code and address don't match. 50 - data validation is requested, but the result is unsuccessful. 51 - invalid format of the AVS/AVV verification request. | DEPOSITED, APPROVED, REVERSED, REFUNDED, DECLINED_BY_TIMEOUT, DECLINED_CARDPRESENT |

### Code examples

#### Symmetric cryptography

##### Java

```java
package net.payrdr.test;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.util.Comparator;
import java.util.Map;
import java.util.stream.Collector;

public class SymmetricCryptographyExample {

    private static final String secretToken = "ooc7slpvc61k7sf7ma7p4hrefr";
    private static final Map<String, String> callbackParams = Map.of(
            "checksum", "EAF2FB72CAB99FD5067F4BA493DD84F4D79C1589FDE8ED29622F0F07215AA972",
            "mdOrder", "06cf5599-3f17-7c86-bdbc-bd7d00a8b38b",
            "operation", "approved",
            "orderNumber", "2003",
            "status", "1"
    );

    public static void main(String[] args) throws Exception {
        String signedString = callbackParams.entrySet().stream()
                .filter(entry -> !entry.getKey().equals("checksum"))
                .sorted(Map.Entry.comparingByKey(Comparator.naturalOrder()))
                .collect(Collector.of(
                        StringBuilder::new,
                        (accumulator, element) -> accumulator
                                .append(element.getKey()).append(";")
                                .append(element.getValue()).append(";"),
                        StringBuilder::append,
                        StringBuilder::toString
                ));

        byte[] mac = generateHMacSHA256(secretToken.getBytes(), signedString.getBytes());
        String signature = callbackParams.get("checksum");

        boolean verified = verifyMac(signature, mac);
        System.out.println("signature verification result: " + verified);
    }

    private static boolean verifyMac(String signature, byte[] mac) {
        return signature.equals(bytesToHex(mac));
    }

    public static byte[] generateHMacSHA256(byte[] hmacKeyBytes, byte[] dataBytes) throws Exception {
        SecretKeySpec secretKey = new SecretKeySpec(hmacKeyBytes, "HmacSHA256");

        Mac hMacSHA256 = Mac.getInstance("HmacSHA256");
        hMacSHA256.init(secretKey);

        return hMacSHA256.doFinal(dataBytes);
    }

    private static String bytesToHex(byte[] bytes) {
        final byte[] HEX_ARRAY = "0123456789ABCDEF".getBytes(StandardCharsets.US_ASCII);
        byte[] hexChars = new byte[bytes.length * 2];
        for (int j = 0; j < bytes.length; j++) {
            int v = bytes[j] & 0xFF;
            hexChars[j * 2] = HEX_ARRAY[v >>> 4];
            hexChars[j * 2 + 1] = HEX_ARRAY[v & 0x0F];
        }
        return new String(hexChars, StandardCharsets.UTF_8);
    }
}
```

#### Asymmetric cryptography

##### Java

```java
package net.payrdr.test;

import java.io.ByteArrayInputStream;
import java.io.InputStream;
import java.security.Signature;
import java.security.cert.CertificateFactory;
import java.security.cert.X509Certificate;
import java.util.Base64;
import java.util.Comparator;
import java.util.Map;
import java.util.stream.Collector;

public class AsymmetricCryptographyExample {

    private static final Map<String, String> callbackParams = Map.of(
            "amount", "35000099",
            "sign_alias", "SHA-256 with RSA",
            "checksum", "163BD9FAE437B5DCDAAC4EB5ECEE5E533DAC7BD2C8947B0719F7A8BD17C101EBDBEACDB295C10BF041E903AF3FF1E6101FF7DB9BD024C6272912D86382090D5A7614E174DC034EBBB541435C80869CEED1F1E1710B71D6EE7F52AE354505A83A1E279FBA02572DC4661C1D75ABF5A7130B70306CAFA69DABC2F6200A698198F8",
            "mdOrder", "12b59da8-f68f-7c8d-12b5-9da8000826ea",
            "operation", "deposited",
            "status", "1");

    private static final String certificate =
            "MIICcTCCAdqgAwIBAgIGAWAnZt3aMA0GCSqGSIb3DQEBCwUAMHwxIDAeBgkqhkiG9w0BCQEWEWt6" +
                    "bnRlc3RAeWFuZGV4LnJ1MQswCQYDVQQGEwJSVTESMBAGA1UECBMJVGF0YXJzdGFuMQ4wDAYDVQQH" +
                    "EwVLYXphbjEMMAoGA1UEChMDUkJTMQswCQYDVQQLEwJRQTEMMAoGA1UEAxMDUkJTMB4XDTE3MTIw" +
                    "NTE2MDEyMFoXDTE4MTIwNTE2MDExOVowfDEgMB4GCSqGSIb3DQEJARYRa3pudGVzdEB5YW5kZXgu" +
                    "cnUxCzAJBgNVBAYTAlJVMRIwEAYDVQQIEwlUYXRhcnN0YW4xDjAMBgNVBAcTBUthemFuMQwwCgYD" +
                    "VQQKEwNSQlMxCzAJBgNVBAsTAlFBMQwwCgYDVQQDEwNSQlMwgZ8wDQYJKoZIhvcNAQEBBQADgY0A" +
                    "MIGJAoGBAJNgxgtWRFe8zhF6FE1C8s1t/dnnC8qzNN+uuUOQ3hBx1CHKQTEtZFTiCbNLMNkgWtJ/" +
                    "CRBBiFXQbyza0/Ks7FRgSD52qFYUV05zRjLLoEyzG6LAfihJwTEPddNxBNvCxqdBeVdDThG81zC0" +
                    "DiAhMeSwvcPCtejaDDSEYcQBLLhDAgMBAAEwDQYJKoZIhvcNAQELBQADgYEAfRP54xwuGLW/Cg08" +
                    "ar6YqhdFNGq5TgXMBvQGQfRvL7W6oH67PcvzgvzN8XCL56dcpB7S8ek6NGYfPQ4K2zhgxhxpFEDH" +
                    "PcgU4vswnhhWbGVMoVgmTA0hEkwq86CA5ZXJkJm6f3E/J6lYoPQaKatKF24706T6iH2htG4Bkjre" +
                    "gUA=";

    public static void main(String[] args) throws Exception {

        String signedString = callbackParams.entrySet().stream()
                .filter(entry -> !entry.getKey().equals("checksum") && !entry.getKey().equals("sign_alias"))
                .sorted(Map.Entry.comparingByKey(Comparator.naturalOrder()))
                .collect(Collector.of(
                        StringBuilder::new,
                        (accumulator, element) -> accumulator
                                .append(element.getKey()).append(";")
                                .append(element.getValue()).append(";"),
                        StringBuilder::append,
                        StringBuilder::toString
                ));

        InputStream publicCertificate = new ByteArrayInputStream(Base64.getDecoder().decode(certificate));
        String signature = callbackParams.get("checksum");

        boolean verified = checkSignature(signedString.getBytes(), signature.getBytes(), publicCertificate);
        System.out.println("signature verification result: " + verified);
    }

    private static boolean checkSignature(byte[] signedString, byte[] signature, InputStream publicCertificate) throws Exception {
        CertificateFactory certFactory = CertificateFactory.getInstance("X.509");
        X509Certificate x509Cert = (X509Certificate) certFactory.generateCertificate(publicCertificate);

        Signature signatureAlgorithm = Signature.getInstance("SHA512withRSA");
        signatureAlgorithm.initVerify(x509Cert.getPublicKey());
        signatureAlgorithm.update(signedString);

        return signatureAlgorithm.verify(decodeHex(new String(signature)));
    }

    private static byte[] decodeHex(String hex) {
        int l = hex.length();
        byte[] data = new byte[l / 2];
        for (int i = 0; i < l; i += 2) {
            data[i / 2] = (byte) ((Character.digit(hex.charAt(i), 16) << 4)
                    + Character.digit(hex.charAt(i + 1), 16));
        }
        return data;
    }
}
```

#### Symmetric cryptography

##### PHP

```php
<?php

$data = 'amount;123456;mdOrder;3ff6962a-7dcc-4283-ab50-a6d7dd3386fe;operation;deposited;orderNumber;10747;status;1;';
$key = 'yourSecretToken';
$hmac = hash_hmac ( 'sha256' , $data , $key);

echo "[$hmac]\n";
?>
```

1. Assign the string value to data variable.
2. Assign the private key value to the key variable.
3. hash_hmac function ( 'sha256', $data, $key) calculates the checksum of the passed string using the private key and SHA-256 algorithm.
4. Save the function output in hmac variable.
5. Use echo function to create an output.
6. Compare this value with the one passed in the callback notification.

#### Asymmetric cryptography

##### PHP

```php
<?php
// data from response
$data = 'amount;35000099;mdOrder;12b59da8-f68f-7c8d-12b5-9da8000826ea;operation;deposited;status;1;';
$checksum = '9524FD765FB1BABFB1F42E4BC6EF5A4B07BAA3F9C809098ACBB462618A9327539F975FEDB4CF6EC1556FF88BA74774342AF4F5B51BA63903BE9647C670EBD962467282955BD1D57B16935C956864526810870CD32967845EBABE1C6565C03F94FF66907CEDB54669A1C74AC1AD6E39B67FA7EF6D305A007A474F03B80FD6C965656BEAA74E09BB1189F4B32E622C903DC52843C454B7ACF76D6F76324C27767DE2FF6E7217716C19C530CA7551DB58268CC815638C30F3BCA3270E1FD44F63C14974B108E65C20638ECE2F2D752F32742FFC5077415102706FA5235D310D4948A780B08D1B75C8983F22F211DFCBF14435F262ADDA6A97BFEB6D332C3D51010B';

// your public key (e.g. SHA-512 with RSA)
// if you have a CERT, please see openssl_get_publickey()
$publicKey = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwtuGKbQ4WmfdV1gjWWys
5jyHKTWXnxX3zVa5/Cx5aKwJpOsjrXnHh6l8bOPQ6Sgj3iSeKJ9plZ3i7rPjkfmw
qUOJ1eLU5NvGkVjOgyi11aUKgEKwS5Iq5HZvXmPLzu+U22EUCTQwjBqnE/Wf0hnI
wYABDgc0fJeJJAHYHMBcJXTuxF8DmDf4DpbLrQ2bpGaCPKcX+04POS4zVLVCHF6N
6gYtM7U2QXYcTMTGsAvmIqSj1vddGwvNGeeUVoPbo6enMBbvZgjN5p6j3ItTziMb
Vba3m/u7bU1dOG2/79UpGAGR10qEFHiOqS6WpO7CuIR2tL9EznXRc7D9JZKwGfoY
/QIDAQAB
-----END PUBLIC KEY-----
EOD;

$binarySignature = hex2bin(strtolower($checksum));
$isVerify = openssl_verify($data, $binarySignature, $publicKey, OPENSSL_ALGO_SHA512);
if ($isVerify == 1) {
    echo "signature ok\n";
} elseif ($isVerify == 0) {
    echo "bad (there's something wrong)\n";
} else {
    echo "error checking signature\n";
}
?>
```

eCommerce

API V1

