# Auditoría de Seguridad Chatbot e Inteligencia Artificial — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Asistente:** Miss Lorna (Google Gemini)  
**Alcance:** Conexiones, API Keys, prompt injection, contexto, exposición de información, límites de consumo

---

## 1. Arquitectura del Chatbot

### Componentes

| Componente | Archivo | Responsabilidad |
|------------|---------|-----------------|
| Endpoint chat | `backend/app/Http/Controllers/Api/ChatController.php` | Recibe mensaje, construye contexto, llama a Gemini |
| Servicio Gemini | `backend/app/Services/GeminiService.php` | Llama a Google Gemini API |
| Servicio RAG | `backend/app/Services/RagKnowledgeService.php` | Genera embeddings, busca chunks de conocimiento |
| Modelo KnowledgeChunk | `backend/app/Models/KnowledgeChunk.php` | Almacena chunks con embeddings en MySQL |
| Ruta | `POST /chat` | `auth:api`, `jwt.fresh`, `throttle:chat` |

### Flujo de una consulta

1. Cliente envía `message` (max 4000), `context` (opcional, max 12000), `history` (opcional)
2. Backend valida entrada
3. `RagKnowledgeService::buildContext()` busca chunks relevantes en `knowledge_chunks` (cosine similarity, topK=4)
4. `GeminiService::chat()` envía mensaje + historial + contexto a Google Gemini API
5. Retorna respuesta al cliente

---

## 2. Protección de API Keys

### Hallazgo AI-01: GEMINI_API_KEY vacía en .env
- **Estado:** `GEMINI_API_KEY=` (vacío) en `backend/.env`
- **Impacto:** El chatbot no funciona (`503: El asistente aún no está configurado`)
- **Archivo:** `backend/.env`

### Hallazgo AI-02: API Key enviada en URL query string
- **Causa raíz:** `GeminiService::chat()` construye la URL como:
  ```
  https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={$apiKey}
  ```
- **Riesgo:** La API Key aparece en:
  - Logs de proxy/load balancer
  - Historial del navegador (si se expone)
  - Logs de servidores intermedios
- **Mitigación:** Google recomienda enviar la API key en el header `Authorization: Bearer <key>` o `x-goog-api-key: <key>`, no en la URL.
- **Archivo:** `backend/app/Services/GeminiService.php` (líneas 57-58)

### Hallazgo AI-03: GEMINI_API_KEY en .env (no hardcodeada)
- **Positivo:** La clave está en variable de entorno, no en código fuente.
- **Riesgo residual:** Si `.env` se expone (backup, logging, commit accidental), la clave se compromete.

---

## 3. Prompt Injection

### Sistema prompt (`GeminiService::SYSTEM_PROMPT`)
- Define a "Miss Lorna" como asistente oficial de OCCRE
- Instrucciones en español sobre cómo responder
- Instruye a no inventar tarifas, requisitos o contactos
- Limita temperatura a 0.4 y maxOutputTokens a 800

### Evaluación de protección contra prompt injection

| Técnica de ataque | ¿Protegido? | Observación |
|------------------|-------------|-------------|
| Ignorar instrucciones anteriores | Parcial | El SYSTEM_PROMPT es fuerte, pero un usuario podría intentar "olvida todo lo anterior" |
| Extracción del system prompt | No protegido | Un usuario podría pedir "repita sus instrucciones" y el modelo podría revelar el SYSTEM_PROMPT |
| Inyección indirecta vía contexto | No protegido | El `context` enviado por la app se inserta en el system instruction sin sanitización |
| Manipulación de historial | Parcial | Historial limitado a 10 entradas, rol usuario/modelo validado |

### Hallazgo AI-04: No hay filtrado de prompt injection en la entrada del usuario
- **Causa raíz:** El `message` del usuario se envía directamente a Gemini sin filtrado de intentos de prompt injection.
- **Riesgo:** Un usuario podría intentar extraer el system prompt, manipular el comportamiento del asistente, o intentar obtener información sensible.
- **Archivo:** `backend/app/Http/Controllers/Api/ChatController.php` (líneas 64-70), `backend/app/Services/GeminiService.php`

---

## 4. Manipulación del Contexto

### Contexto RAG (`RagKnowledgeService::buildContext`)
- Busca chunks de `knowledge_chunks` basado en similitud de coseno
- Los chunks se indexan desde `procedure_types` (trámites activos) y `database/knowledge/knowledge_base.json`
- El contexto se añade al system instruction de Gemini

### Hallazgo AI-05: RAG no filtra por permisos de usuario
- **Causa raíz:** `RagKnowledgeService::search()` consulta TODOS los knowledge_chunks sin filtrar por usuario.
- **Riesgo:** Si el knowledge base contiene información que no debería ser pública (procedimientos internos, datos de contacto internos), cualquier usuario autenticado puede obtenerla.
- **Archivo:** `backend/app/Services/RagKnowledgeService.php` (líneas 144-178)

### Hallazgo AI-06: Contexto enviado por el cliente no se valida contra el usuario
- **Causa raíz:** El parámetro `context` (max 12000) se acepta tal como lo envía el cliente y se inserta en el system instruction.
- **Riesgo:** Un cliente malicioso podría enviar contexto manipulado para influir en las respuestas del asistente (ataque de inyección de contexto).
- **Archivo:** `backend/app/Http/Controllers/Api/ChatController.php` (líneas 74-79)

---

## 5. Exposición de Información Privada

### Datos enviados a Gemini
Cada petición a Gemini incluye:
1. SYSTEM_PROMPT (estático, público)
2. Contexto RAG (conocimiento público de trámites)
3. Contexto enviado por el cliente (opcional)
4. Historial de conversación (últimos 10 mensajes)
5. Mensaje actual del usuario

### Evaluación
- **Positivo:** No se envían datos personales del usuario (nombre, documento, etc.) a Gemini automáticamente.
- **Riesgo:** Si el usuario incluye sus datos personales en el `message`, esos datos se envían a Google Gemini (tercero). Esto es una decisión del usuario, no una vulnerabilidad del sistema.
- **Riesgo:** Si el `context` enviado por la app contiene PII del usuario, se envía a Gemini.

---

## 6. Acceso Indebido a Registros

### Evaluación
- El chatbot **no tiene acceso directo a la base de datos de usuarios, solicitudes o documentos**.
- El RAG solo accede a `knowledge_chunks` (información pública de trámites).
- **Positivo:** El chatbot está aislado de los datos privados de ciudadanos.

---

## 7. Control de Herramientas

- El chatbot no tiene herramientas ejecutivas (no puede crear solicitudes, cambiar estados, subir documentos).
- Solo responde con texto.
- **Positivo:** Superficie de ataque limitada.

---

## 8. Límites de Consumo y Protección contra Abuso

| Límite | Implementación | Evaluación |
|--------|---------------|------------|
| Rate limiting en ruta `/chat` | `throttle:chat` | Positivo |
| Longitud del message | max 4000 caracteres | Positivo |
| Longitud del context | max 12000 caracteres | Positivo |
| Historial | Últimas 10 entradas | Positivo |
| maxOutputTokens en Gemini | 800 | Positivo |
| Temperature | 0.4 | Positivo (respuestas determinísticas) |
| Timeout de HTTP | 30 segundos | Positivo |

### Hallazgo AI-07: No hay límite de consumo por usuario para el chatbot
- **Causa raíz:** `throttle:chat` aplica rate limiting general, no por usuario específico.
- **Riesgo:** Un usuario podría saturar el chatbot y el presupuesto de Gemini API.
- **Archivo:** `backend/routes/api.php` (línea 177)

---

## 9. Seguridad de las Respuestas

### Hallazgo AI-08: Respuestas de Gemini no se sanitizan antes de devolver al cliente
- **Causa raíz:** El texto retornado por Gemini se devuelve tal cual en `['answer' => $answer]`.
- **Riesgo:** Si Gemini retorna contenido malicioso (script, enlaces de phishing), el frontend lo renderizaría.
- **Mitigación frontend:** No se observó uso de `innerHTML` ni `bypassSecurityTrust` en `src/` (verificado anteriormente), por lo que Angular sanitiza por defecto.
- **Archivo:** `backend/app/Http/Controllers/Api/ChatController.php` (línea 97)

---

## 10. Evaluación OWASP Top 10 for LLM Applications (2025)

| # | Riesgo OWASP LLM | Estado en OCCRE | Evaluación |
|---|-----------------|-----------------|------------|
| LLM01 | Prompt Injection | Parcial | SYSTEM_PROMPT fuerte, pero sin filtrado de entrada |
| LLM02 | Insecure Output Handling | Bajo | Respuesta devuelta como texto, Angular sanitiza |
| LLM03 | Training Data Poisoning | N/A | No se entrena el modelo |
| LLM04 | Model Denial of Service | Medio | Rate limiting general, no por usuario |
| LLM05 | Supply Chain | Bajo | Usa Gemini API gestionada por Google |
| LLM06 | Disclosure of Sensitive Info | Bajo | No se envían PII al modelo automáticamente |
| LLM07 | Insecure Plugin Design | N/A | No hay plugins/tools |
| LLM08 | Excessive Agency | N/A | El chatbot no ejecuta acciones |
| LLM09 | Overreliance | Medio | Users may trust incorrect info (mitigado por system prompt) |
| LLM10 | Model Theft | N/A | Modelo gestionado por Google |

---

## 11. Resumen de Hallazgos IA/Chatbot

| ID | Hallazgo | Severidad | Estado |
|----|----------|-----------|--------|
| AI-01 | GEMINI_API_KEY vacía (chatbot no configurado) | Informativo | Pendiente (configuración de usuario) |
| AI-02 | API Key en URL query string | Alta | Pendiente (cambiar a header Authorization) |
| AI-03 | No filtrado de prompt injection | Media | Pendiente |
| AI-04 | Contexto del cliente no validado | Media | Pendiente |
| AI-05 | RAG no filtra por permisos de usuario | Media | Pendiente |
| AI-06 | No hay límite de consumo por usuario | Media | Pendiente |
| AI-07 | Respuestas de Gemini no sanitizadas | Baja | Mitigado por Angular (no innerHTML) |

---

## 12. Recomendaciones (requieren autorización)

1. Mover `GEMINI_API_KEY` de URL query string a header `Authorization` o `x-goog-api-key`.
2. Implementar filtrado básico de prompt injection en el mensaje del usuario.
3. Validar que el contexto enviado por el cliente no contenga intentos de manipulación.
4. Considerar filtrado RAG por permisos si el knowledge base se expande con información interna.
5. Implementar rate limiting por usuario para el chatbot.
6. Configurar `GEMINI_API_KEY` en entorno de producción.
