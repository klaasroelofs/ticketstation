// Installs a package zip on staging through the Joomla MCP server's install_extension tool.
// Usage (from the repository root): node build/install_staging.js .mcp.json dist/pkg_ticketstation_<version>.zip
const fs = require('fs');
const [, , cfgPath, zipPath] = process.argv;
const cfg = JSON.parse(fs.readFileSync(cfgPath, 'utf8')).mcpServers.joomla;
const url = cfg.args[1];
const auth = cfg.env.AUTH_HEADER;

let session = null;
async function rpc(id, method, params) {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json, text/event-stream',
    'Authorization': auth,
  };
  if (session) headers['Mcp-Session-Id'] = session;
  const body = { jsonrpc: '2.0', method, params };
  if (id !== null) body.id = id;
  const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body) });
  const sid = res.headers.get('mcp-session-id');
  if (sid) session = sid;
  const text = await res.text();
  return { status: res.status, text };
}

(async () => {
  const init = await rpc(1, 'initialize', {
    protocolVersion: '2025-03-26',
    capabilities: {},
    clientInfo: { name: 'install-script', version: '1.0' },
  });
  console.log('initialize:', init.status);
  await rpc(null, 'notifications/initialized', {});
  const content = fs.readFileSync(zipPath).toString('base64');
  const r = await rpc(2, 'tools/call', { name: 'install_extension', arguments: { content } });
  console.log('install:', r.status);
  console.log(r.text.slice(0, 4000));
})().catch(e => { console.error(e); process.exit(1); });
