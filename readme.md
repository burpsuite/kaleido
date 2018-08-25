# Kaleido(Scope)

## Capture
```json
{
  "logType": "leancloud",
  "leancloud": {
    "appId": "pTPoK9Q7jGyTFpNLX8LKtOsz-MdYXbMMI",
    "appKey": "uiTYApLfNDT03aiCKOxEUTNx",
    "masterKey": "okrmXc4OT2hY1jUfKI7xzmUt",
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
