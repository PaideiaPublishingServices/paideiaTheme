#!/usr/bin/env python3
"""Genera docs/index.html (es) y docs/{en,pt,id}/index.html.

Uso: python3 docs/_build/build.py

GitHub Pages (Jekyll) no publica las carpetas que empiezan con "_".
Los textos de cada idioma están en strings.py; la estructura, en
template.html ({{clave}} se reemplaza por el texto).
"""
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.dirname(HERE)
sys.path.insert(0, HERE)
from strings import LANGS, STRINGS  # noqa: E402

BASE_URL = 'https://paideiapublishingservices.github.io/paideiaTheme/'

template = open(os.path.join(HERE, 'template.html'), encoding='utf-8').read()

for code, info in LANGS.items():
    s = dict(STRINGS[code])
    prefix = '' if info['dir'] == '' else '../'
    s['lang'] = info['html']
    s['root'] = prefix
    s['url'] = BASE_URL + (info['dir'] + '/' if info['dir'] else '')
    s['langnav'] = '\n'.join(
        '\t\t\t<a href="{href}" hreflang="{hl}" lang="{hl}" title="{name}"{cur}>{short}</a>'.format(
            href=prefix + (o['dir'] + '/' if o['dir'] else ''),
            hl=o['html'], name=o['name'], short=o['short'],
            cur=' aria-current="page"' if c == code else '')
        for c, o in LANGS.items())
    s['alternates'] = '\n'.join(
        '<link rel="alternate" hreflang="{hl}" href="{u}">'.format(
            hl=o['html'], u=BASE_URL + (o['dir'] + '/' if o['dir'] else ''))
        for o in LANGS.values()) + '\n<link rel="alternate" hreflang="x-default" href="' + BASE_URL + '">'

    def sub(m):
        key = m.group(1)
        if key not in s:
            raise KeyError(f'{code}: falta la clave {key}')
        return s[key]

    html = re.sub(r'\{\{(\w+)\}\}', sub, template)
    out_dir = os.path.join(DOCS, info['dir']) if info['dir'] else DOCS
    os.makedirs(out_dir, exist_ok=True)
    with open(os.path.join(out_dir, 'index.html'), 'w', encoding='utf-8') as f:
        f.write(html)
    print('ok', os.path.relpath(os.path.join(out_dir, 'index.html'), DOCS))
