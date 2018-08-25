# Kaleido(Scope)
Kaleido network traffic forwarding tool(api-gateway).

## Description
  * Support GET, POST, PUT, DELETE, PATCH, SEARCH and More Request Methods.
  * Support Added/Delete/Modify Http Headers(Request/Response).
  * Support for Replay Request(Current)/Response(History).
  * Support Added/Delete/Modify Cookies(Request/Response).
  * Support Regular Expressions to Added/Delete/Modify Body(Request/Response).
  * Support Capture Request and Responses.
  * Support for Remote Server Status-Code Responses.
  * Support Response Size Limit.

## Usage
Env Variable: DATABASE
```json
{
  "loadType": "remote",
  "loadInfo": {
    "loadData": "http://localhost/kaleido_v3.json",
    "loadCache": {
      "type": "dynamic",
      "data": "REDIS_URL",
      "interval": 86400
    }
  }
}
```

## Capture
Env Variable: Capture
```json
{
  "logType": "leancloud",
  "leancloud": {
    "appId": "pTPoK9Q7jGyTFpNXXXXXXXX-MdYXbMMI",
    "appKey": "uiTYApLfNDXXXXXXXXxEUTNx",
    "masterKey": "okrmXcXXXXXXXXUfKI7xzmUt",
    "apiServer": "https://us-api.leancloud.cn",
    "className": "TrafficCapture"
  }
}
```

## Maybe Later
  * Support Stream (react/stream).
  * Support Tag Replace.
  * Support Capture to Redis.
  * Support for More Exception Handling.
  * ...
