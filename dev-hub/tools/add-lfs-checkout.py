#!/usr/bin/env python3
"""Add `lfs: true` to every actions/checkout step in .github/workflows/*.yml|yaml.

Handles both styles:
  - name: Checkout            - uses: actions/checkout@<sha> # v7
    uses: actions/checkout@x    with:
    with:                         lfs: true
      lfs: true
Idempotent: skips steps that already have lfs: true.
"""
import glob
import re

USE_RE = re.compile(r'^(\s*)(?:-\s*)?(uses):\s*actions/checkout@\S+')
WITH_RE = re.compile(r'^(\s*)with:\s*$')
KEY_RE = re.compile(r'^(\s*)([\w.-]+):')

changed_files = []
for path in sorted(glob.glob('.github/workflows/*.yml') + glob.glob('.github/workflows/*.yaml')):
    with open(path, 'r', encoding='utf-8') as fh:
        lines = fh.readlines()

    out = []
    i = 0
    modified = False
    while i < len(lines):
        m = USE_RE.match(lines[i])
        if not m:
            out.append(lines[i])
            i += 1
            continue

        indent = len(m.group(1))          # whitespace before '-' or 'uses'
        dash_style = bool(lines[i].lstrip().startswith('- uses:'))
        if dash_style:
            key_col = indent + 2          # 'with:' aligns with 'uses'
        else:
            key_col = indent              # 'with:' aligns with 'uses:'
        sub_col = key_col + 2

        # Look for an existing with: block (sibling key of uses)
        j = i + 1
        while j < len(lines) and lines[j].strip() == '':
            j += 1
        with_block = None
        if j < len(lines):
            wm = WITH_RE.match(lines[j])
            if wm and len(wm.group(1)) == key_col:
                with_block = (j, key_col)

        if with_block is not None:
            j, with_indent = with_block
            k = j + 1
            has_lfs = False
            while k < len(lines):
                km = KEY_RE.match(lines[k])
                if not km or len(km.group(1)) <= with_indent:
                    break
                if km.group(2) == 'lfs':
                    has_lfs = True
                k += 1
            if not has_lfs:
                out.extend(lines[i:j + 1])
                out.append(f'{" " * (with_indent + 2)}lfs: true\n')
                out.extend(lines[j + 1:k])
                i = k
                modified = True
                continue
            out.append(lines[i])
            i += 1
            continue

        # No with: block — insert one aligned as sibling of uses
        out.append(lines[i])
        out.append(f'{" " * key_col}with:\n')
        out.append(f'{" " * sub_col}lfs: true\n')
        i += 1
        modified = True

    if modified:
        with open(path, 'w', encoding='utf-8') as fh:
            fh.writelines(out)
        changed_files.append(path)

print(f'{len(changed_files)} workflow(s) modifié(s) :')
for p in changed_files:
    print(f'  - {p}')
