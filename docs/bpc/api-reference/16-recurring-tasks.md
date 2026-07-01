> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Recurring tasks API

The below API allows you to configure tasks for recurring payments. The configured payments are futher automatically executed according to the created schedule.

## Create task

The request used to create a recurrent task is `https://dev.bpcbt.com/recurrent/v1/task/create`.

When sending the request, you should use the header: `Content-Type: application/json`

Currently it is possible to create recurrent tasks only for stored credential payments, without 3 DS authorization.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `locale` | String [2] | Language in ISO 639-1. If not specified, the default language will be used. |
| Optional | `merchantLogin` | String [1..30] | To create a task on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `task` | Object | Information about the recurring task being created. See nested parameters. |

Below are the parameters of the `task` block (data about the recurrent task being created).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. |
| Optional | `cardHolder` | String [1..26] | The name of the holder of the card in Latin characters. |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Mandatory | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `expiry` | Integer | Card expiration in the following format: YYYYMM. |
| Mandatory | `merchantTaskUuid` | String | The unique identifier of the task being created within the merchant. |
| Optional | `pan` | String [1..19] | Masked number of the card that has been used for the payment. |
| Optional | `attributes` | Object | A set of task attributes, structure: {name1:value1,…,nameN:valueN}. The exact list of attributes should be coordinated with the bank. |
| Optional | `params` | Object | A set of additional free-form parameters, structure: {name1:value1,…,nameN:valueN} |
| Mandatory | `scheduleData` | Object | A set of task frequency shedule data. See nested parameters. |

Below are the parameters of the `scheduleData` block (data about the task frequency shedule).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `scheduledSince` | String [26] | Date/time from which the task should start. Format: yyyy-MM-dd’T’HH:mm:ss.SSSZ |
| Mandatory | `scheduledTill` | String [26] | Date/time before which the task should be completed. Format: yyyy-MM-dd’T’HH:mm:ss.SSSZ |
| Mandatory | `timeUnit` | String | Time unit. Allowed values: Nanos, Micros, Millis, Seconds, Minutes, Hours, HalfDays, Days, Weeks, Months, Years, Decades, Centuries, Millennia, Eras, Forever |
| Mandatory | `value` | integer | Frequency value |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `status` | String | Response status. Allowed values: SUCCESS, FAIL. |
| Mandatory | `task` | Object | Information about the created recurring task. See nested parameters. |

Below are the parameters of the `task` block (data about the created recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `created` | String | Date/time the task was created. |
| Mandatory | `merchantLogin` | String | Merchant login. |
| Mandatory | `merchantTaskUuid` | String | The unique identifier of the task being created within the merchant. |
| Mandatory | `nextPaymentDate` | String | Next payment date. |
| Mandatory | `state` | String | Task state. Allowed values:  CREATED - created, no payments yet ACTIVE - created and at least one payment has been already proceeded FAILED - exceeded max payment attempts and deactivated COMPLETED - reached EOL date, all payments completed TERMINATED - terminated by either client via processing or merchant EXPIRED - card expired |
| Mandatory | `taskUuid` | String | The unique identifier of the task within the recurring payments service. |

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/recurrent/v1/task/create \
--header 'Content-Type: application/json' \
--data '{
    "username":"test_user",
    "password":"test_user_password",
    "locale":"en",
    "task":{
        "merchantTaskUuid":"c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c82",
        "clientId":"TestClient",
        "bindingId": "5eb094e1-4a96-7b33-af5f-a29407a73a93",
        "scheduleData": {
            "value":"1",
            "timeUnit":"DAYS",
            "scheduledSince":"2024-01-24T00:00:00.000+0300",
            "scheduledTill":"2024-02-24T00:00:00.000+0300"
        },
        "amount":100,
        "currency":170,
        "params":{
            "description":"desc",
            "phone":"576015555556"
        }
    }
}'
```

#### Example of a success response

```json
{
  "status": "SUCCESS",
  "task": {
    "created": "2024-01-24T10:23:35.434591+03:00",
    "merchantLogin": "testMerch",
    "taskUuid": "8a6a5350-1be3-456e-8e81-e5c7eafbd699",
    "nextPaymentDate": "2024-01-24T00:00:00+03:00",
    "state": "CREATED",
    "merchantTaskUuid": "c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c82"
  }
}
```

## Modify task

The request used to modify an existing recurrent task is `https://dev.bpcbt.com/recurrent/v1/task/modify`.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `locale` | String [2] | Language in ISO 639-1. If not specified, the default language will be used. |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `task` | Object | Information about the recurring task being modified. See nested parameters. |

Below are the parameters of the `task` block (data about the recurrent task being modified).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. |
| Optional | `cardholder` | String | Cardholder's name in Latin characters. |
|  |  |  |  |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `expiry` | String [6] | Card expiration in the following format: YYYYMM. |
| Optional | `pan` | String [1..19] | Masked number of the card that has been used for the payment. |
| Optional | `attributes` | Object | A set of task attributes, structure: {name1:value1,…,nameN:valueN}. The exact list of attributes should be coordinated with the bank. |
| Optional | `params` | Object | A set of additional free-form parameters, structure: {name1:value1,…,nameN:valueN} |
| Mandatory | `taskIdentifier` | Object | The unique identifier or set of identifiers of the task. See nested parameters. |

Below are the parameters of the `taskIdentifier` block (set of identifiers for a recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `merchantLogin` | String | Merchant login. Should be present for searching by merchantTaskUuid. |
| Optional | `merchantTaskUuid` | String | The unique identifier of the task being created within the merchant. Required if taskUuid is not present. |
| Optional | `taskUuid` | String | The unique identifier of the task within the recurring payments service. Required if merchantTaskUuid is not present. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `status` | String | Response status. Allowed values: SUCCESS, FAIL. |
| Mandatory | `task` | Object | Information about the created recurring task. See nested parameters. |

Below are the parameters of the `task` block (data about the modified recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `updated` | String | Date/time of the last update of the task. |
| Mandatory | `merchantLogin` | String | Merchant login. |
| Mandatory | `merchantTaskUuid` | String | The unique identifier of the task within the merchant. |
| Mandatory | `taskUuid` | String | The unique identifier of the task within the recurring payments service. |

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/recurrent/v1/task/modify \
--header 'Content-Type: application/json' \
--data '{
    "username":"test_user",
    "password":"test_user_password",
    "task":{
        "taskIdentifier": {
           "taskUuid":"9ae9f36d-0ba3-4686-87c4-a5ec77c562a4"
       },
        "bindingId":"5eb094e1-4a96-7b33-af5f-a29407a73a93",
        "clientId":"TestClient",
        "params":{
            "description":"new description",
            "phone":"+576015555558"
        }
}'
```

#### Example of a success response

```json
{
  "status": "SUCCESS",
  "task": {
    "updated": "2024-01-23T14:10:03.730644+03:00",
    "merchantLogin": "testMerch",
    "taskUuid": "9ae9f36d-0ba3-4686-87c4-a5ec77c562a4",
    "merchantTaskUuid": "c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c80"
  }
}
```

## Get task information

The request used to get information about a recurrent task is `https://dev.bpcbt.com/recurrent/v1/task/get`.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `locale` | String [2] | Language in ISO 639-1. If not specified, the default language will be used. |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `taskIdentifier` | Object | The unique identifier or set of identifiers of the task. See nested parameters. |

Below are the parameters of the `taskIdentifier` block (set of identifiers for a recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `merchantLogin` | String | Merchant login. Should be present for searching by merchantTaskUuid. |
| Optional | `merchantTaskUuid` | String | The unique identifier of the task being created within the merchant. Required if taskUuid is not present. |
| Optional | `taskUuid` | String | The unique identifier of the task within the recurring payments service. Required if merchantTaskUuid is not present. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `status` | String | Response status. Allowed values: SUCCESS, FAIL. |
| Mandatory | `task` | Object | Information about the recurrent task. See nested parameters. |

Below are the parameters of the `task` block (data about a recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `attemptsHistory` | Array of objects | All payment attempts in chronological order. Each payment attempt is an attemptsHistory object. See nested parameters. |
| Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. |
| Optional | `cardHolder` | String [1..26] | The name of the holder of the card in Latin characters. |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Mandatory | `created` | String | Date/time the task was created. |
| Mandatory | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `expiry` | Integer [6] | Card expiration in the following format: YYYYMM. |
| Mandatory | `lastPaymentDate` | String | Last payment date. |
| Optional | `maskedPan` | String | Masked pan of the card. |
| Mandatory | `merchantLogin` | String [1..30] | The login of the merchant. |
| Mandatory | `merchantTaskUuid` | String | The unique identifier of the task within the merchant. |
| Mandatory | `nextPaymentDate` | String | Next payment date. |
| Optional | `params` | Object | A set of additional free-form parameters, structure: {name1:value1,…,nameN:valueN} |
| Mandatory | `scheduleData` | Object | A set of task frequency shedule data. See nested parameters. |
| Mandatory | `state` | String | Task state. Allowed values:  CREATED - created, no payments yet ACTIVE - created and at least one payment has been already proceeded FAILED - exceeded max payment attempts and deactivated COMPLETED - reached EOL date, all payments completed TERMINATED - terminated by either client via processing or merchant EXPIRED - card expired |
| Mandatory | `taskUuid` | String | The unique identifier of the task within the recurring payments service. |
| Mandatory | `updated` | String | Date/time of the last update of the task. |

Below are the parameters of the `scheduleData` block (data about the task frequency shedule).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `scheduledSince` | String [26] | Date/time from which the task should start. Format: yyyy-MM-dd’T’HH:mm:ss.SSSZ |
| Mandatory | `scheduledTill` | String [26] | Date/time before which the task should be completed. Format: yyyy-MM-dd’T’HH:mm:ss.SSSZ |
| Mandatory | `timeUnit` | String | Time unit. Allowed values: Nanos, Micros, Millis, Seconds, Minutes, Hours, HalfDays, Days, Weeks, Months, Years, Decades, Centuries, Millennia, Eras, Forever |
| Mandatory | `value` | integer | Frequency value |

Below are the parameters of the `attemptsHistory` block (data about the task frequency shedule).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `executed` | String | Date/time of the payment attempt. Format: yyyy-MM-dd’T’HH:mm:ss.SSSZ |
| Optional | `orderId` | String | Unique identifier of the transaction in the payment gateway |
| Optional | `orderNumber` | String | Order number in the payment gateway |
| Mandatory | `paymentAttemptUuid` | String | Unique identifier of the payment attempt |
| Mandatory | `paymentUuid` | String | Unique identifier of the payment |
| Mandatory | `state` | String | Task state. Allowed values:  CREATED - created, no payments yet ACTIVE - created and at least one payment has been already proceeded FAILED - exceeded max payment attempts and deactivated COMPLETED - reached EOL date, all payments completed TERMINATED - terminated by either client via processing or merchant EXPIRED - card expired |
| Mandatory | `technicalAttempt` | Boolean | Flag indicating whether the attempt was technical |

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/recurrent/v1/task/get \
--header 'Content-Type: application/json' \
--data '{
    "username":"test_user",
    "password":"test_user_password",
    "taskIdentifier": {
           "taskUuid":"8a6a5350-1be3-456e-8e81-e5c7eafbd699"
     }
}'
```

#### Example of a success response

```json
{
  "status": "SUCCESS",
  "task": {
    "taskUuid": "8a6a5350-1be3-456e-8e81-e5c7eafbd699",
    "state": "ACTIVE",
    "merchantLogin": "testMerch",
    "bindingId": "5eb094e1-4a96-7b33-af5f-a29407a73a93",
    "clientId": "TestClient",
    "created": "2024-01-24T10:23:35.434591+03:00",
    "updated": "2024-01-24T10:23:35.434591+03:00",
    "lastPaymentDate": "2024-01-24T00:00:00+03:00",
    "nextPaymentDate": "2024-01-25T00:00:00+03:00",
    "scheduleData": {
      "scheduledSince": "2024-01-24T00:00:00.000+0300",
      "scheduledTill": "2024-02-24T00:00:00.000+0300",
      "value": 1,
      "timeUnit": "DAYS"
    },
    "amount": 100,
    "currency": 170,
    "params": {
      "phone": "576015555556",
      "description": "description"
    },
    "attemptsHistory": [
      {
        "paymentAttemptUuid": "6873d7dc-4366-45c5-9de6-bc6e0aa05b3d",
        "paymentUuid": "1a450005-ad46-4474-8940-eb154822296c",
        "state": "SUCCEEDED",
        "executed": "2024-01-24T10:23:41.788436+03:00",
        "technicalAttempt": false,
        "orderId": "d2d56b04-124b-77ab-9034-9f2307a73a93",
        "orderNumber": "E5DFEDE990694FCFB5246AEC2612A355"
      }
    ],
    "merchantTaskUuid": "c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c82"
  }
}
```

## Terminate task

The request used to terminate a recurrent task is `https://dev.bpcbt.com/recurrent/v1/task/terminate`.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `locale` | String [2] | Language in ISO 639-1. If not specified, the default language will be used. |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `taskIdentifier` | Object | The unique identifier or set of identifiers of the task. See nested parameters. |

Below are the parameters of the `taskIdentifier` block (set of identifiers for a recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `merchantLogin` | String | Merchant login. Should be present for searching by merchantTaskUuid. |
| Optional | `merchantTaskUuid` | String | The unique identifier of the task being created within the merchant. Required if taskUuid is not present. |
| Optional | `taskUuid` | String | The unique identifier of the task within the recurring payments service. Required if merchantTaskUuid is not present. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `status` | String | Response status. Allowed values: SUCCESS, FAIL. |
| Mandatory | `task` | Object | Information about the recurrent task. See nested parameters. |

Below are the parameters of the `task` block (data about the terminated recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `updated` | String | Date/time of the last update of the task. |
| Mandatory | `merchantLogin` | String | Merchant login. |
| Mandatory | `merchantTaskUuid` | String | The unique identifier of the task within the merchant. |
| Mandatory | `taskUuid` | String | The unique identifier of the task within the recurring payments service. |
| Mandatory | `state` | String | Task state. Allowed values:  CREATED - created, no payments yet ACTIVE - created and at least one payment has been already proceeded FAILED - exceeded max payment attempts and deactivated COMPLETED - reached EOL date, all payments completed TERMINATED - terminated by either client via processing or merchant EXPIRED - card expired |

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/recurrent/v1/task/terminate \
--header 'Content-Type: application/json' \
--data '{
    "username":"test_user",
    "password":"test_user_password",
    "taskIdentifier": {
        "merchantTaskUuid": "c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c82",
        "merchantLogin": "testMerch"
    }
}'
```

#### Example of a success response

```json
{
  "status": "SUCCESS",
  "task": {
    "updated": "2024-01-24T10:23:35.434591+03:00",
    "merchantLogin": "testMerch",
    "taskUuid": "8a6a5350-1be3-456e-8e81-e5c7eafbd699",
    "state": "TERMINATED",
    "merchantTaskUuid": "c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c82"
  }
```

## Activate task

The request used to activate a recurrent task is `https://dev.bpcbt.com/recurrent/v1/task/activate`.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `locale` | String [2] | Language in ISO 639-1. If not specified, the default language will be used. |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `taskIdentifier` | Object | The unique identifier or set of identifiers of the task. See nested parameters. |

Below are the parameters of the `taskIdentifier` block (set of identifiers for a recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `merchantLogin` | String | Merchant login. Should be present for searching by merchantTaskUuid. |
| Optional | `merchantTaskUuid` | String | The unique identifier of the task being created within the merchant. Required if taskUuid is not present. |
| Optional | `taskUuid` | String | The unique identifier of the task within the recurring payments service. Required if merchantTaskUuid is not present. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `status` | String | Response status. Allowed values: SUCCESS, FAIL. |
| Mandatory | `task` | Object | Information about the recurrent task. See nested parameters. |

Below are the parameters of the `task` block (data about the activated recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `updated` | String | Date/time of the last update of the task. |
| Mandatory | `merchantLogin` | String | Merchant login. |
| Mandatory | `merchantTaskUuid` | String | The unique identifier of the task within the merchant. |
| Mandatory | `nextPaymentDate` | String | Next payment date. |
| Mandatory | `taskUuid` | String | The unique identifier of the task within the recurring payments service. |
| Mandatory | `state` | String | Task state. Allowed values:  CREATED - created, no payments yet ACTIVE - created and at least one payment has been already proceeded FAILED - exceeded max payment attempts and deactivated COMPLETED - reached EOL date, all payments completed TERMINATED - terminated by either client via processing or merchant EXPIRED - card expired |

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/recurrent/v1/task/activate \
--header 'Content-Type: application/json' \
--data '{
    "username":"test_user",
    "password":"test_user_password",
    "taskIdentifier": {
        "merchantTaskUuid": "c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c82",
        "merchantLogin": "testMerch"
    }
}'
```

#### Example of a success response

```json
{
  "status": "SUCCESS",
  "task": {
    "updated": "2024-01-24T10:23:35.434591+03:00",
    "merchantLogin": "testMerch",
    "taskUuid": "8a6a5350-1be3-456e-8e81-e5c7eafbd699",
    "state": "ACTIVE",
    "nextPaymentDate": "2024-01-25T00:00:00+03:00",
    "merchantTaskUuid": "c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c82"
  }
}
```

## Terminate tasks

The request used to terminate multiple recurrent tasks is `https://dev.bpcbt.com/recurrent/v1/task/batchTerminate`.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `locale` | String [2] | Language in ISO 639-1. If not specified, the default language will be used. |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `taskIdentifiers` | Array of Objects | Identifiers of tasks to be terminated. Each task is a taskIdentifier object. See nested parameters. |

Below are the parameters of the `taskIdentifier` block (set of identifiers for a recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `merchantLogin` | String | Merchant login. Should be present for searching by merchantTaskUuid. |
| Optional | `merchantTaskUuid` | String | The unique identifier of the task being created within the merchant. Required if taskUuid is not present. |
| Optional | `taskUuid` | String | The unique identifier of the task within the recurring payments service. Required if merchantTaskUuid is not present. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `status` | String | Response status. Allowed values: SUCCESS, FAIL. |

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/recurrent/v1/task/batchTerminate \
--header 'Content-Type: application/json' \
--data '{
    "locale":"EN",
    "username":"testUser",
    "password":"testPwd",
    "tasksIdentifiers":[
        {
            "taskUuid":"0ba73819-65f8-43c4-9dc4-3870cb10416b"
        },
        {
            "taskUuid":"0ba73820-65f8-43c4-9dc4-3870cb10416b"            
        }
    ]
}'
```

#### Example of a success response

```json
{   
  "status": "SUCCESS" 
}
```

## Skip payment

The request used to skip a particular payment of a recurrent task is `https://dev.bpcbt.com/recurrent/v1/payment/skip`.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `locale` | String [2] | Language in ISO 639-1. If not specified, the default language will be used. |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `taskIdentifier` | Object | The unique identifier or set of identifiers of the task. See nested parameters. |
| Mandatory | `paymentNumber` | Integer | Number of the recurring payment to be skipped. |

Below are the parameters of the `taskIdentifier` block (set of identifiers for a recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `merchantLogin` | String | Merchant login. Should be present for searching by merchantTaskUuid. |
| Optional | `merchantTaskUuid` | String | The unique identifier of the task being created within the merchant. Required if taskUuid is not present. |
| Optional | `taskUuid` | String | The unique identifier of the task within the recurring payments service. Required if merchantTaskUuid is not present. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `status` | String | Response status. Allowed values: SUCCESS, FAIL. |
| Mandatory | `task` | Object | Information about the recurrent task. See nested parameters. |

Below are the parameters of the `task` block (data about the activated recurrent task).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `updated` | String | Date/time of the last update of the task. |
| Mandatory | `merchantLogin` | String | Merchant login. |
| Mandatory | `merchantTaskUuid` | String | The unique identifier of the task within the merchant. |
| Mandatory | `nextPaymentDate` | String | Next payment date. |
| Mandatory | `taskUuid` | String | The unique identifier of the task within the recurring payments service. |
| Mandatory | `state` | String | Task state. Allowed values:  CREATED - created, no payments yet ACTIVE - created and at least one payment has been already proceeded FAILED - exceeded max payment attempts and deactivated COMPLETED - reached EOL date, all payments completed TERMINATED - terminated by either client via processing or merchant EXPIRED - card expired |

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/recurrent/v1/payment/skip \
--header 'Content-Type: application/json' \
--data '{
    "locale":"EN",
    "username":"test_user",
    "password":"test_user_password",
    "taskIdentifier": {
           "taskUuid":"7ae881fb-aee0-4446-883c-8087512bd26a"
     },
    "paymentNumber": 2
}'
```

#### Example of a success response

```json
{
  "status": "SUCCESS",
  "task": {
    "updated": "2024-01-24T10:23:35.434591+03:00",
    "merchantLogin": "testMerch",
    "taskUuid": "8a6a5350-1be3-456e-8e81-e5c7eafbd699",
    "state": "ACTIVE",
    "nextPaymentDate": "2024-01-25T00:00:00+03:00",
    "merchantTaskUuid": "c0fdc30e-0ba9-4d14-ac0b-44fe9d4d7c82"
  }
}
```

