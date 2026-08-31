import csv
from pathlib import Path

csv.field_size_limit(max(csv.field_size_limit(), 10_000_000))

src = Path("raw.csv")
dst = Path("cleaned_for_supabase.csv")

if not src.exists():
    raise SystemExit(f"File not found: {src}")

with src.open("r", encoding="utf-8-sig", newline="") as f:
    rows = list(csv.reader(f))

if not rows:
    raise SystemExit("CSV is empty")

header = [
    "id","order_number","customer_name","phone","email","device","issue",
    "estimated_cost","status","date_received","end_date","images",
    "components_changed","updates","created_at","updated_at"
]

cleaned = []

for row in rows:
    if not row or all(cell.strip() == "" for cell in row):
        continue

    if len(row) > len(header):
        row = row[:len(header)]
    elif len(row) < len(header):
        row += [""] * (len(header) - len(row))

    cleaned.append(row)

if not cleaned:
    raise SystemExit("No valid rows found to import")

# Remove a first data-row-as-header if it clearly is not a real header.
if cleaned[0][0].strip().lower() not in {"id", "order_number"} and cleaned[0][0].strip() != "":
    start_index = 1
else:
    start_index = 0

with dst.open("w", encoding="utf-8", newline="") as f:
    writer = csv.writer(f, quoting=csv.QUOTE_ALL)
    writer.writerow(header)
    for row in cleaned[start_index:]:
        writer.writerow(row)

print(f"Clean CSV written to {dst}")