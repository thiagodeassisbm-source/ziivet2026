const fs = require("fs");
const path = require("path");

function rtrim(str) {
  return str.replace(/\s+$/u, "");
}

function escapeRegExp(s) {
  return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function findMatchingParen(text, openParenIdx) {
  // Ignores parentheses inside single/double quoted strings
  let depth = 0;
  let inSingle = false;
  let inDouble = false;
  let escape = false;

  for (let i = openParenIdx; i < text.length; i++) {
    const ch = text[i];

    if (escape) {
      escape = false;
      continue;
    }

    if (ch === "\\") {
      escape = true;
      continue;
    }

    if (inSingle) {
      if (ch === "'") inSingle = false;
      continue;
    }
    if (inDouble) {
      if (ch === '"') inDouble = false;
      continue;
    }

    if (ch === "'") {
      inSingle = true;
      continue;
    }
    if (ch === '"') {
      inDouble = true;
      continue;
    }

    if (ch === "(") depth++;
    if (ch === ")") {
      depth--;
      if (depth === 0) return i;
    }
  }
  throw new Error("Unbalanced parentheses while parsing CREATE TABLE.");
}

function parseCreateTableBlocks(sql) {
  const blocks = [];
  const re = /CREATE\s+TABLE\s+`([^`]+)`\s*\(/giu;

  for (;;) {
    const m = re.exec(sql);
    if (!m) break;
    const table = m[1];
    const start = m.index;
    // open paren is the last char of the regex match (it includes '(')
    const openParen = sql.indexOf("(", re.lastIndex - 1);
    if (openParen < 0) continue;
    const closeParen = findMatchingParen(sql, openParen);
    const semicolon = sql.indexOf(";", closeParen);
    if (semicolon < 0) continue;
    const end = semicolon + 1;
    blocks.push({ start, end, table, openParen, closeParen });
  }

  blocks.sort((a, b) => a.start - b.start);
  return blocks;
}

function hasPrimaryKeyInBlock(blockSql) {
  return /PRIMARY\s+KEY\s*\(/iu.test(blockSql);
}

function findTablesWithPkViaAlter(sql) {
  const pkRe = /ALTER\s+TABLE\s+`([^`]+)`[\s\S]*?\bADD\s+PRIMARY\s+KEY\b/giu;
  const set = new Set();
  let m;
  while ((m = pkRe.exec(sql))) {
    set.add(m[1]);
  }
  return set;
}

function pickPkColumn(inside) {
  const candidates = ["pk_id", "pk_id_autoinc", "pk_id_autoinc2"];
  for (const c of candidates) {
    const re = new RegExp("`" + escapeRegExp(c) + "`", "iu");
    if (!re.test(inside)) return c;
  }
  return "pk_id_autoinc2";
}

function injectPkIntoCreateTable(sql, block) {
  const blockSql = sql.slice(block.start, block.end);
  const relOpen = block.openParen - block.start;
  const relClose = block.closeParen - block.start;
  const inside = blockSql.slice(relOpen + 1, relClose);

  const col = pickPkColumn(inside);
  const insideTrim = rtrim(inside);

  let insideNew = inside;
  if (insideTrim.endsWith(",")) {
    insideNew =
      inside +
      `\n  \`${col}\` int(11) NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (\`${col}\`)\n`;
  } else {
    insideNew =
      inside +
      `,\n  \`${col}\` int(11) NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (\`${col}\`)\n`;
  }

  const prefix = blockSql.slice(0, relOpen + 1); // includes '('
  const suffix = blockSql.slice(relClose); // starts at ')'
  return prefix + insideNew + suffix;
}

function main() {
  const projectRoot = path.resolve(__dirname, "..");
  const inputSql = path.join(projectRoot, "banco de dados sql", "u315410518_ziipvet.sql");
  const outputSql = path.join(projectRoot, "banco de dados sql", "u315410518_ziipvet_fixed_pk.sql");

  if (!fs.existsSync(inputSql)) {
    throw new Error("Arquivo de entrada não encontrado: " + inputSql);
  }

  const sql = fs.readFileSync(inputSql, "utf8");
  const blocks = parseCreateTableBlocks(sql);
  if (!blocks.length) {
    throw new Error("Nenhum CREATE TABLE encontrado no dump.");
  }

  const pkViaAlter = findTablesWithPkViaAlter(sql);
  const createTableHasPk = new Map();
  for (const b of blocks) {
    const blockSql = sql.slice(b.start, b.end);
    createTableHasPk.set(b.table, hasPrimaryKeyInBlock(blockSql));
  }

  const missing = [];
  for (const [table, hasPk] of createTableHasPk.entries()) {
    if (!hasPk && !pkViaAlter.has(table)) missing.push(table);
  }
  missing.sort();

  console.log(`Tabelas sem PRIMARY KEY (estimado): ${missing.length}`);
  for (let i = 0; i < Math.min(missing.length, 80); i++) console.log(" - " + missing[i]);
  if (missing.length > 80) console.log(" - ... (lista truncada)");

  // Safety check: INSERT sem lista de colunas quebraria ao adicionar coluna.
  for (const t of missing) {
    const re = new RegExp(
      `INSERT\\s+INTO\\s+\`${escapeRegExp(t)}\`\\s+VALUES\\s*\\(`,
      "iu"
    );
    if (re.test(sql)) {
      throw new Error(
        `Formato de INSERT sem lista de colunas detectado para \`${t}\`. Ajustar automaticamente INSERT é mais complexo; preciso que você me avise.`
      );
    }
  }

  const missingSet = new Set(missing);
  let lastEnd = 0;
  const out = [];
  for (const b of blocks) {
    out.push(sql.slice(lastEnd, b.start));
    const blockSql = sql.slice(b.start, b.end);
    if (missingSet.has(b.table) && !hasPrimaryKeyInBlock(blockSql)) {
      out.push(injectPkIntoCreateTable(sql, b));
    } else {
      out.push(blockSql);
    }
    lastEnd = b.end;
  }
  out.push(sql.slice(lastEnd));

  fs.writeFileSync(outputSql, out.join(""), "utf8");
  console.log("\nArquivo gerado:", outputSql);
}

main();

