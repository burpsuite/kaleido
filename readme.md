# Kaleido(Scope)

## Capture (ENV: Capture)
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

## Configuration (ENV: DATABASE)
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
