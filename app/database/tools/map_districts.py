import json
import os

# Paths relative to repository root
BASE_DIR = os.path.join(os.path.dirname(__file__), '..', 'database', 'data')
# Adjusted to current file location
BASE_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'data'))

PROVINCES_INPUT = os.path.join(BASE_DIR, 'provinces.json')
DATASET_PROVINCES = os.path.join(BASE_DIR, 'province.json')
DATASET_DISTRICTS = os.path.join(BASE_DIR, 'district.json')
OUTPUT_PATH = os.path.join(BASE_DIR, 'districts_mapped.json')

print('Using base dir:', BASE_DIR)

# --- load your provinces list (id 1..77) ---
with open(PROVINCES_INPUT, 'r', encoding='utf-8') as f:
    provinces_input = json.load(f)

# map: province_name -> your province_id
name_to_pid = {str(p["name"]).strip(): int(p["id"]) for p in provinces_input}

# --- load dataset provinces/districts ---
with open(DATASET_PROVINCES, 'r', encoding='utf-8') as f:
    dataset_provinces = json.load(f)

with open(DATASET_DISTRICTS, 'r', encoding='utf-8') as f:
    dataset_districts = json.load(f)

# dataset: province_id (internal) -> province_name
ds_pid_to_name = {int(p["id"]): str(p["name"]).strip() for p in dataset_provinces}

out = []
next_did = 1

missing_provinces = set()

for d in dataset_districts:
    ds_province_id = int(d["province_id"])
    prov_name = ds_pid_to_name.get(ds_province_id, '').strip()
    if not prov_name:
        continue

    your_pid = name_to_pid.get(prov_name)
    if not your_pid:
        missing_provinces.add(prov_name)
        continue

    out.append({
        "district_id": next_did,
        "district_name": str(d["name"]).strip(),
        "province_id": your_pid
    })
    next_did += 1

with open(OUTPUT_PATH, 'w', encoding='utf-8') as f:
    json.dump(out, f, ensure_ascii=False, indent=2)

print(f"✅ districts written: {len(out)} rows -> {OUTPUT_PATH}")

if missing_provinces:
    print("⚠️ provinces missing mapping (name mismatch):")
    for n in sorted(missing_provinces):
        print("-", n)
