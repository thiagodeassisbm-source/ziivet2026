import re
from dataclasses import dataclass
from pathlib import Path


@dataclass
class CreateTableBlock:
    start: int
    end: int
    table: str
    open_paren: int
    close_paren: int


def find_matching_paren(text: str, open_paren_idx: int) -> int:
    """
    Finds the matching ')' for the '(' at open_paren_idx.
    Parentheses inside quoted strings are ignored.
    """
    depth = 0
    in_single_quote = False
    in_double_quote = False
    escape = False

    for i in range(open_paren_idx, len(text)):
        ch = text[i]

        if escape:
            escape = False
            continue

        if ch == "\\":
            escape = True
            continue

        if in_single_quote:
            if ch == "'":
                in_single_quote = False
            continue

        if in_double_quote:
            if ch == '"':
                in_double_quote = False
            continue

        if ch == "'":
            in_single_quote = True
            continue
        if ch == '"':
            in_double_quote = True
            continue

        if ch == "(":
            depth += 1
        elif ch == ")":
            depth -= 1
            if depth == 0:
                return i

    raise ValueError("Unbalanced parentheses while parsing CREATE TABLE.")


def parse_create_table_blocks(sql: str) -> list[CreateTableBlock]:
    blocks: list[CreateTableBlock] = []
    # We search for CREATE TABLE `name` ( ... ) ...
    create_re = re.compile(r"CREATE\s+TABLE\s+`([^`]+)`\s*\(", re.IGNORECASE)

    for m in create_re.finditer(sql):
        table = m.group(1)
        start = m.start()
        open_paren = sql.find("(", m.end() - 1)
        if open_paren < 0:
            continue
        close_paren = find_matching_paren(sql, open_paren)
        semicolon = sql.find(";", close_paren)
        if semicolon < 0:
            continue
        end = semicolon + 1
        blocks.append(CreateTableBlock(start=start, end=end, table=table, open_paren=open_paren, close_paren=close_paren))

    # blocks may contain duplicates if patterns appear; keep as-is but sorted by start
    blocks.sort(key=lambda b: b.start)
    return blocks


def has_primary_key_in_block(block_sql: str) -> bool:
    return re.search(r"PRIMARY\s+KEY\s*\(", block_sql, re.IGNORECASE) is not None


def find_tables_with_pk_via_alter(sql: str) -> set[str]:
    # ALTER TABLE `t` ADD PRIMARY KEY (`...`)
    pk_re = re.compile(
        r"ALTER\s+TABLE\s+`([^`]+)`[\s\S]*?\bADD\s+PRIMARY\s+KEY\b",
        re.IGNORECASE,
    )
    return set(pk_re.findall(sql))


def pick_pk_column(existing_inside: str, prefer: str = "pk_id") -> str:
    if re.search(rf"`{re.escape(prefer)}`", existing_inside):
        alt = "pk_id_autoinc"
        if re.search(rf"`{re.escape(alt)}`", existing_inside):
            return "pk_id_autoinc2"
        return alt
    return prefer


def inject_pk_into_create_table(create_block_sql: str, block: CreateTableBlock) -> str:
    # Replace the inside of (...) with an extra pk column + PRIMARY KEY
    inside = create_block_sql[block.open_paren + 1 - block.start: block.close_paren - block.start]
    # Determine whether we can safely append
    tail_trim = inside.rstrip()
    if tail_trim.endswith(","):
        inside_new = inside + f"\n  `{pick_pk_column(inside)}` int(11) NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`{pick_pk_column(inside)}`)\n"
    else:
        # Add a comma before the first appended line
        inside_new = inside + f",\n  `{pick_pk_column(inside)}` int(11) NOT NULL AUTO_INCREMENT,\n  PRIMARY KEY (`{pick_pk_column(inside)}`)\n"

    # Ensure we don't accidentally create double spaces issues: keep everything else intact
    prefix = create_block_sql[: block.open_paren + 1 - block.start]
    suffix = create_block_sql[block.close_paren - block.start:]
    return prefix + inside_new + suffix


def main():
    project_root = Path(__file__).resolve().parents[1]
    input_sql = project_root / "banco de dados sql" / "u315410518_ziipvet.sql"
    output_sql = project_root / "banco de dados sql" / "u315410518_ziipvet_fixed_pk.sql"

    if not input_sql.exists():
        raise SystemExit(f"Arquivo de entrada não encontrado: {input_sql}")

    sql = input_sql.read_text(encoding="utf-8", errors="ignore")

    blocks = parse_create_table_blocks(sql)
    if not blocks:
        raise SystemExit("Nenhum CREATE TABLE encontrado no dump.")

    pk_via_alter = find_tables_with_pk_via_alter(sql)

    # Determine missing PK tables by checking each CREATE TABLE block.
    create_table_has_pk: dict[str, bool] = {}
    for b in blocks:
        create_block_sql = sql[b.start:b.end]
        create_table_has_pk[b.table] = has_primary_key_in_block(create_block_sql)

    missing_pk = []
    for table, has_pk in create_table_has_pk.items():
        if not has_pk and table not in pk_via_alter:
            missing_pk.append(table)

    missing_pk = sorted(set(missing_pk))
    print(f"Tabelas sem PRIMARY KEY (estimado): {len(missing_pk)}")
    for t in missing_pk[:50]:
        print(f" - {t}")
    if len(missing_pk) > 50:
        print(" - ... (lista truncada)")

    # Safety check: inserts for missing_pk should include column list.
    # If there are inserts like: INSERT INTO `table` VALUES (...)
    # then adding a new column will break them. We detect that.
    for t in missing_pk:
        if re.search(rf"INSERT\s+INTO\s+`{re.escape(t)}`\s+VALUES\s*\(", sql, re.IGNORECASE):
            raise SystemExit(
                f"Formato de INSERT sem lista de colunas detectado para `{t}`. "
                f"Não é seguro adicionar coluna sem ajustar INSERT. "
                f"Checa o dump e me avisa."
            )

    missing_set = set(missing_pk)
    # Build output by replacing CREATE TABLE blocks for missing tables.
    out_parts = []
    last_end = 0
    for b in blocks:
        out_parts.append(sql[last_end:b.start])
        create_block_sql = sql[b.start:b.end]
        if b.table in missing_set:
            # Ensure we don't duplicate if somehow pk already exists
            if has_primary_key_in_block(create_block_sql):
                out_parts.append(create_block_sql)
            else:
                out_parts.append(inject_pk_into_create_table(create_block_sql, b))
        else:
            out_parts.append(create_block_sql)
        last_end = b.end
    out_parts.append(sql[last_end:])

    output_sql.write_text("".join(out_parts), encoding="utf-8")
    print(f"\nArquivo gerado: {output_sql}")


if __name__ == "__main__":
    main()

