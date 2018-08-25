# Kaleido(Scope)
Kaleido network traffic forwarding tool(api-gateway).

## Description
  * Support GET, POST, PUT, DELETE, PATCH, SEARCH and More Request Methods.
  * Support Added/Delete/Modify Http Headers(Request/Response).
  * Support for Replay Request(Current)/Response(History).
  * Support Added/Delete/Modify Cookies(Request/Response).
  * Support Regular Expressions to Modify Request Url.
  * Support Regular Expressions to Added/Delete/Modify Body(Request/Response).
  * Support Patch Request/Response Json-Body(Does not support JSONP).
  * Support Capture Request and Responses.
  * Support for Remote Server Status-Code Responses.
  * Support Response Body Size Limit(By byte).
  * Support Regular Expressions to Added/Delete/Modify Request Params.
  * Support for Allowing/Denying the Display of Remote Server Status-Code.
  * ...

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

## Example
```json
{
  "5b36788ee0e42": {
    "desc": "httpbin",
    "host": ["http://httpbin.org", "https://httpbin.org"],
    "method": ["get", "post", "put"],
    "handle": {
      "request": {
        "check_hostname": true,
        "check_method": true,
        "fix_urlencode": true,
        "enable_header": true,
        "enable_cookie": true,
        "maxSize": 250000,
        "url": {},
        "url_param": {},
        "form_param": {},
        "body": {},
        "body_patch": {},
        "cookie": {},
        "header": {
          "Cookie": null
        }
      },
      "response": {
        "allow_error": true,
        "enable_header": true,
        "enable_cookie": true,
        "body": {},
        "body_patch": {},
        "cookie": {},
        "header": {
          "Connection": null,
          "Transfer-Encoding": null,
          "Content-Length": null,
          "Access-Control-Allow-Origin": "*"
        }
      }
    }
  }
}
```

## Maybe Later
  * Support Stream (react/stream).
  * Support Tag Replace.
  * Support Capture to Redis.
  * Support for More Exception Handling.
  * ...
