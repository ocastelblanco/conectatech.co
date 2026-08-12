import { S3Client, GetObjectCommand, PutObjectCommand, DeleteObjectCommand, HeadObjectCommand } from "@aws-sdk/client-s3";
import { getSignedUrl } from "@aws-sdk/s3-request-presigner";
import { randomUUID } from "crypto";

const s3 = new S3Client({ region: "us-east-1" });
const BUCKET = "assets.conectatech.co";

// ─── PDFs ─────────────────────────────────────────────────────────────────────
const IDX_KEY = "recursos/pdf/index.json";
const PREFIX  = "recursos/pdf/";

// ─── Imágenes ─────────────────────────────────────────────────────────────────
const IMG_IDX_KEY = "recursos/img/index.json";
const IMG_PREFIX  = "recursos/img/";
const IMG_TYPES   = { png: "image/png", jpg: "image/jpeg", jpeg: "image/jpeg", webp: "image/webp", gif: "image/gif" };

const ADMIN_ORIGINS = new Set(["https://admin.conectatech.co"]);
const READ_ORIGINS  = new Set(["https://conectatech.co", "https://www.conectatech.co"]);

function getPerm(event) {
  const o = (event.headers?.origin || event.headers?.Origin || "").replace(/\/$/, "");
  if (ADMIN_ORIGINS.has(o)) return "admin";
  if (READ_ORIGINS.has(o))  return "read";
  return "none";
}

function res(status, body, extra = {}) {
  return {
    statusCode: status,
    headers: { "Content-Type": "application/json", "Access-Control-Allow-Origin": "*", ...extra },
    body: JSON.stringify(body),
  };
}

async function readIndex(key) {
  try {
    const r = await s3.send(new GetObjectCommand({ Bucket: BUCKET, Key: key }));
    return JSON.parse(await r.Body.transformToString());
  } catch (e) {
    if (e.name === "NoSuchKey") return [];
    throw e;
  }
}

// Lee-modifica-escribe el índice de forma atómica usando escritura condicional
// de S3 (If-Match / If-None-Match sobre el ETag). Si otra invocación concurrente
// escribió el índice entre la lectura y la escritura, S3 rechaza el PUT con 412
// y se reintenta desde una lectura fresca — evita que dos subidas cercanas en
// el tiempo se pisen y una de ellas pierda su registro silenciosamente.
//
// `mutatorFn(items)` muta el array en memoria y puede devolver:
//   - un objeto con `abort: true` para no escribir nada (p. ej. id no encontrado)
//   - cualquier otro valor, que se retorna al llamador tras escribir con éxito
async function mutateIndex(key, mutatorFn) {
  const MAX_ATTEMPTS = 6;

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    let items, etag;
    try {
      const r = await s3.send(new GetObjectCommand({ Bucket: BUCKET, Key: key }));
      items = JSON.parse(await r.Body.transformToString());
      etag  = r.ETag;
    } catch (e) {
      if (e.name === "NoSuchKey") { items = []; etag = null; }
      else throw e;
    }

    const result = mutatorFn(items);
    if (result && result.abort) return result;

    try {
      await s3.send(new PutObjectCommand({
        Bucket: BUCKET, Key: key,
        Body: JSON.stringify(items, null, 2),
        ContentType: "application/json", CacheControl: "no-cache, no-store",
        ...(etag ? { IfMatch: etag } : { IfNoneMatch: "*" }),
      }));
      return result;
    } catch (e) {
      const isConflict = e.name === "PreconditionFailed" || e.$metadata?.httpStatusCode === 412;
      if (isConflict && attempt < MAX_ATTEMPTS) continue;
      throw e;
    }
  }
}

export const handler = async (event) => {
  const method = event.requestContext?.http?.method ?? event.httpMethod ?? "GET";
  const path   = event.requestContext?.http?.path   ?? event.path ?? "/";
  const perm   = getPerm(event);

  if (method === "OPTIONS") {
    return {
      statusCode: 204,
      headers: {
        "Access-Control-Allow-Origin": "*",
        "Access-Control-Allow-Methods": "GET,POST,PATCH,DELETE,OPTIONS",
        "Access-Control-Allow-Headers": "Content-Type",
        "Access-Control-Max-Age": "86400",
      },
      body: "",
    };
  }

  try {

    // ─── PDF routes ───────────────────────────────────────────────────────────

    // GET /pdfs
    if (method === "GET" && path === "/pdfs") {
      if (perm === "none") return res(403, { error: "Forbidden" });
      return res(200, { items: await readIndex(IDX_KEY) });
    }

    // POST /pdfs — prepara subida, devuelve URL pre-firmada
    if (method === "POST" && path === "/pdfs") {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const { title, filename } = JSON.parse(event.body || "{}");
      if (!title || !filename) return res(400, { error: "title y filename requeridos" });

      const id  = randomUUID();
      const ext = (filename.split(".").pop() || "pdf").toLowerCase();
      const s3key = `${PREFIX}${id}.${ext}`;
      const contentType = "application/pdf";
      const now = new Date().toISOString();

      const uploadUrl = await getSignedUrl(s3,
        new PutObjectCommand({ Bucket: BUCKET, Key: s3key, ContentType: contentType }),
        { expiresIn: 900 });

      const item = {
        id, title, filename: `${id}.${ext}`, originalFilename: filename,
        s3key, url: `https://assets.conectatech.co/${s3key}`,
        status: "pending", createdAt: now, updatedAt: now,
      };

      await mutateIndex(IDX_KEY, (items) => { items.push(item); });
      return res(201, { item, uploadUrl, contentType });
    }

    // POST /pdfs/{id}/confirm
    const pdfConfirmM = path.match(/^\/pdfs\/([^/]+)\/confirm$/);
    if (method === "POST" && pdfConfirmM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = pdfConfirmM[1];

      const preItem = (await readIndex(IDX_KEY)).find(i => i.id === id);
      if (!preItem) return res(404, { error: "PDF no encontrado" });
      try { await s3.send(new HeadObjectCommand({ Bucket: BUCKET, Key: preItem.s3key })); }
      catch { return res(400, { error: "Archivo aún no disponible en S3" }); }

      const now = new Date().toISOString();
      const result = await mutateIndex(IDX_KEY, (items) => {
        const idx = items.findIndex(i => i.id === id);
        if (idx === -1) return { abort: true };
        items[idx].status = "active";
        items[idx].updatedAt = now;
        return { item: items[idx] };
      });
      if (result.abort) return res(404, { error: "PDF no encontrado" });
      return res(200, { item: result.item });
    }

    const pdfItemM = path.match(/^\/pdfs\/([^/]+)$/);

    // PATCH /pdfs/{id} — renombrar título
    if (method === "PATCH" && pdfItemM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = pdfItemM[1];
      const { title } = JSON.parse(event.body || "{}");
      const now = new Date().toISOString();
      const result = await mutateIndex(IDX_KEY, (items) => {
        const idx = items.findIndex(i => i.id === id);
        if (idx === -1) return { abort: true };
        if (title) items[idx].title = title;
        items[idx].updatedAt = now;
        return { item: items[idx] };
      });
      if (result.abort) return res(404, { error: "PDF no encontrado" });
      return res(200, { item: result.item });
    }

    // GET /pdfs/{id}/download — URL pre-firmada de descarga
    const pdfDlM = path.match(/^\/pdfs\/([^/]+)\/download$/);
    if (method === "GET" && pdfDlM) {
      if (perm === "none") return res(403, { error: "Forbidden" });
      const id = pdfDlM[1];
      const items = await readIndex(IDX_KEY);
      const item = items.find(i => i.id === id);
      if (!item) return res(404, { error: "PDF no encontrado" });
      const downloadUrl = await getSignedUrl(s3,
        new GetObjectCommand({
          Bucket: BUCKET, Key: item.s3key,
          ResponseContentDisposition: `attachment; filename="${item.originalFilename || item.filename}"`,
        }),
        { expiresIn: 300 });
      return res(200, { downloadUrl });
    }

    // DELETE /pdfs/{id}
    if (method === "DELETE" && pdfItemM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = pdfItemM[1];
      const result = await mutateIndex(IDX_KEY, (items) => {
        const idx = items.findIndex(i => i.id === id);
        if (idx === -1) return { abort: true };
        const [removed] = items.splice(idx, 1);
        return { removed };
      });
      if (result.abort) return res(404, { error: "PDF no encontrado" });
      try { await s3.send(new DeleteObjectCommand({ Bucket: BUCKET, Key: result.removed.s3key })); } catch {}
      return res(200, { deleted: true, id });
    }

    // ─── Imagen routes ────────────────────────────────────────────────────────

    // GET /imagenes
    if (method === "GET" && path === "/imagenes") {
      if (perm === "none") return res(403, { error: "Forbidden" });
      return res(200, { items: await readIndex(IMG_IDX_KEY) });
    }

    // POST /imagenes — prepara subida
    if (method === "POST" && path === "/imagenes") {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const { title, filename } = JSON.parse(event.body || "{}");
      if (!title || !filename) return res(400, { error: "title y filename requeridos" });

      const id  = randomUUID();
      const ext = (filename.split(".").pop() || "png").toLowerCase();
      const s3key = `${IMG_PREFIX}${id}.${ext}`;
      const contentType = IMG_TYPES[ext] || "application/octet-stream";
      const now = new Date().toISOString();

      const uploadUrl = await getSignedUrl(s3,
        new PutObjectCommand({ Bucket: BUCKET, Key: s3key, ContentType: contentType }),
        { expiresIn: 900 });

      const item = {
        id, title, filename: `${id}.${ext}`, originalFilename: filename,
        s3key, url: `https://assets.conectatech.co/${s3key}`,
        status: "pending", createdAt: now, updatedAt: now,
      };

      await mutateIndex(IMG_IDX_KEY, (items) => { items.push(item); });
      return res(201, { item, uploadUrl, contentType });
    }

    // POST /imagenes/{id}/confirm
    const imgConfirmM = path.match(/^\/imagenes\/([^/]+)\/confirm$/);
    if (method === "POST" && imgConfirmM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = imgConfirmM[1];

      const preItem = (await readIndex(IMG_IDX_KEY)).find(i => i.id === id);
      if (!preItem) return res(404, { error: "Imagen no encontrada" });
      try { await s3.send(new HeadObjectCommand({ Bucket: BUCKET, Key: preItem.s3key })); }
      catch { return res(400, { error: "Archivo aún no disponible en S3" }); }

      const now = new Date().toISOString();
      const result = await mutateIndex(IMG_IDX_KEY, (items) => {
        const idx = items.findIndex(i => i.id === id);
        if (idx === -1) return { abort: true };
        items[idx].status = "active";
        items[idx].updatedAt = now;
        return { item: items[idx] };
      });
      if (result.abort) return res(404, { error: "Imagen no encontrada" });
      return res(200, { item: result.item });
    }

    const imgItemM = path.match(/^\/imagenes\/([^/]+)$/);

    // PATCH /imagenes/{id} — renombrar título
    if (method === "PATCH" && imgItemM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = imgItemM[1];
      const { title } = JSON.parse(event.body || "{}");
      const now = new Date().toISOString();
      const result = await mutateIndex(IMG_IDX_KEY, (items) => {
        const idx = items.findIndex(i => i.id === id);
        if (idx === -1) return { abort: true };
        if (title) items[idx].title = title;
        items[idx].updatedAt = now;
        return { item: items[idx] };
      });
      if (result.abort) return res(404, { error: "Imagen no encontrada" });
      return res(200, { item: result.item });
    }

    // DELETE /imagenes/{id}
    if (method === "DELETE" && imgItemM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = imgItemM[1];
      const result = await mutateIndex(IMG_IDX_KEY, (items) => {
        const idx = items.findIndex(i => i.id === id);
        if (idx === -1) return { abort: true };
        const [removed] = items.splice(idx, 1);
        return { removed };
      });
      if (result.abort) return res(404, { error: "Imagen no encontrada" });
      try { await s3.send(new DeleteObjectCommand({ Bucket: BUCKET, Key: result.removed.s3key })); } catch {}
      return res(200, { deleted: true, id });
    }

    return res(404, { error: "Ruta no encontrada" });
  } catch (e) {
    console.error(e);
    return res(500, { error: "Error interno", detail: e.message });
  }
};
