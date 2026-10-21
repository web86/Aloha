#!/usr/bin/env python3
"""Verify the protected originals; never mutate source files."""
import hashlib
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
manifest = json.loads((root / 'docs/reference-manifest.json').read_text())
failures = []
for entry in manifest['files']:
    path = root / entry['path']
    if not path.is_file():
        failures.append(f"Missing: {entry['path']}")
    elif path.stat().st_size != entry['bytes'] or hashlib.sha256(path.read_bytes()).hexdigest() != entry['sha256']:
        failures.append(f"Changed: {entry['path']}")
if failures:
    raise SystemExit('\n'.join(failures))
print(f"OK: {len(manifest['files'])} original documents/references match SHA-256.")
for path in manifest.get('missing_at_start', []):
    print(f'Absent at M1.0 start (not recreated): {path}')
