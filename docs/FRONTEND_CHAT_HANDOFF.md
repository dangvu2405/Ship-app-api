# Frontend Handoff – Chat API (Ship-app)

## 1) Base URL
- Local: `http://localhost:8080`
- Prefix: `/api`
- Auth: `Authorization: Bearer <token>`

---

## 2) Endpoint mới

### A. Chat thường (JSON)
**POST** `/api/chat/messages`

Body:
```json
{
  "message": "Tôi muốn gửi hàng đi Hà Nội",
  "session_id": "optional-session-id",
  "task": "chat",
  "context": {
    "company_id": 1
  },
  "model": "gemini-2.0-flash"
}
```

`task` hỗ trợ:
- `chat`
- `classify`
- `extract`
- `advice`

Response thành công:
```json
{
  "success": true,
  "message": "Chat response generated",
  "data": {
    "response_text": "...",
    "session_id": "...",
    "message": {
      "id": 1,
      "message": "...",
      "response": "...",
      "model": "gemini-2.0-flash | local-fallback-429 | local-guard",
      "status": "success"
    },
    "cached": false,
    "guarded": false
  }
}
```

> FE nên ưu tiên đọc nội dung trả lời tại: `data.response_text` (fallback: `data.message.response`)

---

### B. Chat stream (SSE format qua POST)
**POST** `/api/chat/messages/stream`

Body giống endpoint JSON.

Server trả theo event stream:
- `event: meta`
- `event: chunk`
- `event: done`
- `event: error` (nếu có)

Ví dụ dữ liệu event:
```text
event: meta
data: {"session_id":"stream-1","cached":false,"guarded":true}

event: chunk
data: {"index":0,"text":"Xin lỗi, hệ thống đang bận..."}

event: done
data: {
  "response_text":"...",
  "result": {"success":true,"message":"Chat response generated","data":{...}},
  "success":true,
  "message":"Chat response generated",
  "data":{...}
}
```

---

## 3) Endpoint phụ trợ
- **GET** `/api/chat/messages?session_id=<id>&limit=30`
- **GET** `/api/chat/sessions?limit=20`

Ví dụ response thật cho endpoint lịch sử:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "session_id": "73a3fd32-205a-4160-8f94-01c9b17ca6ec",
    "messages": [
      {
        "id": 53,
        "message": "test",
        "response": "Xin lỗi, hệ thống đang bận. Vui lòng thử lại sau ít phút.",
        "model": "local-fallback-429",
        "status": "success",
        "created_at": "2026-04-08T18:55:49.000000Z"
      }
    ]
  }
}
```

---

## 4) Hành vi quan trọng FE cần biết

1. Nếu `message.length < 3`:
   - backend không gọi AI
   - trả `model = local-guard`

2. Nếu Gemini bị quota (429):
   - backend trả fallback local
   - `model = local-fallback-429`
   - vẫn `success = true` để UI luôn hiển thị câu trả lời

3. Cache 5 phút:
   - cùng `message + task + context + model` có thể trả lại kết quả cũ
   - kiểm tra cờ `data.cached`

---

## 5) FE parsing chuẩn

### Với JSON endpoint
- Bubble assistant (ưu tiên): `response.data.data.response_text`
- Fallback cũ: `response.data.data.message.response`
- Metadata:
  - `response.data.data.cached`
  - `response.data.data.guarded`
  - `response.data.data.message.model`

### Với lịch sử hội thoại (`GET /api/chat/messages`)
- Danh sách message: `response.data.data.messages`
- User text: `item.message`
- Assistant text: `item.response`
- Model badge: `item.model`
- Status: `item.status`

### Với stream endpoint
- Ghép text từ tất cả `event=chunk` theo thứ tự `index`
- Khi nhận `event=done`, lấy text theo thứ tự ưu tiên:
  1) `done.response_text`
  2) `done.result?.data?.response_text`
  3) `done.data?.response_text`
  4) `done.result?.data?.message?.response`
  5) `done.data?.message?.response`

---

## 6) JS mẫu cho stream (fetch + ReadableStream)

```js
async function streamChat(token, payload, onChunk, onDone, onError) {
  const res = await fetch('http://localhost:8080/api/chat/messages/stream', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify(payload),
  });

  if (!res.ok || !res.body) {
    const txt = await res.text();
    throw new Error(txt || `HTTP ${res.status}`);
  }

  const reader = res.body.getReader();
  const decoder = new TextDecoder('utf-8');
  let buffer = '';

  while (true) {
    const { value, done } = await reader.read();
    if (done) break;

    buffer += decoder.decode(value, { stream: true });
    const events = buffer.split('\n\n');
    buffer = events.pop() || '';

    for (const ev of events) {
      const lines = ev.split('\n');
      const name = lines.find(l => l.startsWith('event:'))?.replace('event:', '').trim();
      const dataLine = lines.find(l => l.startsWith('data:'))?.replace('data:', '').trim();
      if (!name || !dataLine) continue;

      const data = JSON.parse(dataLine);
      if (name === 'chunk') onChunk?.(data.text, data.index);
      if (name === 'done') onDone?.(data);
      if (name === 'error') onError?.(data);
    }
  }
}
```

---

## 7) Checklist FE
- [ ] Với POST chat: render `data.response_text` (fallback `data.message.response`)
- [ ] Với GET history: render danh sách từ `data.messages[*].response`
- [ ] Có UI state cho `cached/guarded`
- [ ] Hiển thị badge model (`gemini-*`, `local-fallback-429`, `local-guard`)
- [ ] Stream parser xử lý đủ `meta/chunk/done/error`
- [ ] Retry nhẹ khi mạng chập chờn
