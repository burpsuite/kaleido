# Kaleido(Scope)
Kaleido network traffic forwarding tool(api-gateway).

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
