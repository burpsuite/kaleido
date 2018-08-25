# Kaleido(Scope)

## Capture
```json
{
  "logType": "leancloud",
  "leancloud": {
    "appId": "pTPoK9Q7jGyTFpNXXXXXXXX-MdYXbMMI",
    "appKey": "uiTYApLfNDXXXXXXXXxEUTNx",
    "masterKey": "okrmXcXXXXXXXXUfKI7xzmUt",
    "endPoint": "https://us-api.leancloud.cn",
    "className": "TrafficCapture"
  }
}
```

## Configuration
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
