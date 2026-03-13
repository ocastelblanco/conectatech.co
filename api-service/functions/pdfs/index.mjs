import { S3Client, GetObjectCommand, PutObjectCommand, DeleteObjectCommand, HeadObjectCommand } from "@aws-sdk/client-s3";
import { getSignedUrl } from "@aws-sdk/s3-request-presigner";
import { randomUUID } from "crypto";

const s3      = new S3Client({ region: "us-east-1" });
const BUCKET  = "assets.conectatech.co";
const IDX_KEY = "recursos/pdf/index.json";
const PREFIX  = "recursos/pdf/";

const ADMIN_ORIGINS = new Set(["https://admin.conectatech.co"]);
const READ_ORIGINS  = new Set(["https://conectatech.co", "https://www.conectatech.co"]);

function getPerm(event) {
  const o = (event.headers?.origin || event.headers?.Origin || "").replace(/\/$/, "");
  if (ADMIN_ORIGINS.has(o)) return "admin";
  if (READ_ORIGINS.has(o))  return "read";
  return "none";
}

function res(status, body, extra = {}) {
  return { statusCode: status,
    headers: { "Content-Type": "application/json", "Access-Control-Allow-Origin": "*", ...extra },
    body: JSON.stringify(body) };
}

async function readIndex() {
  try {
    const r = await s3.send(new GetObjectCommand({ Bucket: BUCKET, Key: IDX_KEY }));
    return JSON.parse(await r.Body.transformToString());
  } catch (e) { if (e.name === "NoSuchKey") return []; throw e; }
}

async function writeIndex(items) {
  await s3.send(new PutObjectCommand({
    Bucket: BUCKET, Key: IDX_KEY,
    Body: JSON.stringify(items, null, 2),
    ContentType: "application/json", CacheControl: "no-cache, no-store",
  }));
}

export const handler = async (event) => {
  const method = event.requestContext?.http?.method ?? event.httpMethod ?? "GET";
  const path   = event.requestContext?.http?.path   ?? event.path ?? "/";
  const perm   = getPerm(event);

  if (method === "OPTIONS") {
    return { statusCode: 204, headers: {
      "Access-Control-Allow-Origin": "*",
      "Access-Control-Allow-Methods": "GET,POST,PATCH,DELETE,OPTIONS",
      "Access-Control-Allow-Headers": "Content-Type",
      "Access-Control-Max-Age": "86400",
    }, body: "" };
  }

  try {
    // GET /pdfs
    if (method === "GET" && path === "/pdfs") {
      if (perm === "none") return res(403, { error: "Forbidden" });
      return res(200, { items: await readIndex() });
    }

    // POST /pdfs — prepara subida, devuelve URL pre-firmada
    if (method === "POST" && path === "/pdfs") {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const { title, filename } = JSON.parse(event.body || "{}");
      if (!title || !filename) return res(400, { error: "title y filename requeridos" });

      const id  = randomUUID();
      const ext = (filename.split(".").pop() || "pdf").toLowerCase();
      const s3key = `${PREFIX}${id}.${ext}`;
      const now = new Date().toISOString();

      const uploadUrl = await getSignedUrl(s3,
        new PutObjectCommand({ Bucket: BUCKET, Key: s3key, ContentType: "application/pdf" }),
        { expiresIn: 900 });

      const item = { id, title, filename: `${id}.${ext}`, originalFilename: filename,
        s3key, url: `https://assets.conectatech.co/${s3key}`,
        status: "pending", createdAt: now, updatedAt: now };

      const items = await readIndex();
      items.push(item);
      await writeIndex(items);
      return res(201, { item, uploadUrl });
    }

    // POST /pdfs/{id}/confirm — confirmar que la subida a S3 terminó
    const confirmM = path.match(/^\/pdfs\/([^/]+)\/confirm$/);
    if (method === "POST" && confirmM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = confirmM[1];
      const items = await readIndex();
      const idx = items.findIndex(i => i.id === id);
      if (idx === -1) return res(404, { error: "PDF no encontrado" });
      try { await s3.send(new HeadObjectCommand({ Bucket: BUCKET, Key: items[idx].s3key })); }
      catch { return res(400, { error: "Archivo aún no disponible en S3" }); }
      items[idx].status = "active";
      items[idx].updatedAt = new Date().toISOString();
      await writeIndex(items);
      return res(200, { item: items[idx] });
    }

    const itemM = path.match(/^\/pdfs\/([^/]+)$/);

    // PATCH /pdfs/{id} — renombrar título
    if (method === "PATCH" && itemM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = itemM[1];
      const { title } = JSON.parse(event.body || "{}");
      const items = await readIndex();
      const idx = items.findIndex(i => i.id === id);
      if (idx === -1) return res(404, { error: "PDF no encontrado" });
      if (title) items[idx].title = title;
      items[idx].updatedAt = new Date().toISOString();
      await writeIndex(items);
      return res(200, { item: items[idx] });
    }

    // GET /pdfs/{id}/download — URL pre-firmada de descarga
    const dlM = path.match(/^\/pdfs\/([^/]+)\/download$/);
    if (method === "GET" && dlM) {
      if (perm === "none") return res(403, { error: "Forbidden" });
      const id = dlM[1];
      const items = await readIndex();
      const item = items.find(i => i.id === id);
      if (!item) return res(404, { error: "PDF no encontrado" });
      const downloadUrl = await getSignedUrl(s3,
        new GetObjectCommand({ Bucket: BUCKET, Key: item.s3key,
          ResponseContentDisposition: `attachment; filename="${item.originalFilename || item.filename}"` }),
        { expiresIn: 300 });
      return res(200, { downloadUrl });
    }

    // DELETE /pdfs/{id}
    if (method === "DELETE" && itemM) {
      if (perm !== "admin") return res(403, { error: "Forbidden" });
      const id = itemM[1];
      const items = await readIndex();
      const idx = items.findIndex(i => i.id === id);
      if (idx === -1) return res(404, { error: "PDF no encontrado" });
      const [removed] = items.splice(idx, 1);
      try { await s3.send(new DeleteObjectCommand({ Bucket: BUCKET, Key: removed.s3key })); } catch {}
      await writeIndex(items);
      return res(200, { deleted: true, id });
    }

    return res(404, { error: "Ruta no encontrada" });
  } catch (e) {
    console.error(e);
    return res(500, { error: "Error interno", detail: e.message });
  }
};
