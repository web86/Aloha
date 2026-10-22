#!/usr/bin/env python3
"""Investor HTTP acceptance, only against disposable localhost:8091."""
import json
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

m = json.loads(Path(sys.argv[1]).read_text())
base = m['base'].rstrip('/')
assert base == 'http://127.0.0.1:8091', 'Refuse the main database'
phase = sys.argv[2] if len(sys.argv) > 2 else 'default'
prefix = '/owners/research/guides' if phase == 'configured' else '/homeowners/invest/guides'
path = prefix + '/choosing-a-vacation-rental/'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None
opener = urllib.request.build_opener(NoRedirect())
report, errors = [], []
def request(url, status, post_id=None, canonical=None, location=None):
    try:
        try:
            r = opener.open(base + url, timeout=20)
        except urllib.error.HTTPError as error:
            r = error
        body = r.read().decode('utf-8')
        assert r.code == status, (url, r.code, status)
        if post_id is not None:
            assert r.headers.get('X-Aloha-Test-Object-ID') == str(post_id), url
        if status == 404:
            assert r.headers.get('X-Aloha-Test-Is-404') == '1', url
        if location:
            assert r.headers.get('Location') == base + location, (url, r.headers.get('Location'))
        if canonical:
            assert re.search(r'<link\b(?=[^>]*rel=[\"\']canonical[\"\'])(?=[^>]*href=[\"\']' + re.escape(base + canonical) + r'[\"\'])[^>]*>', body), (url, 'canonical')
        report.append({'path': url, 'status': r.code, 'id': r.headers.get('X-Aloha-Test-Object-ID'), 'location': r.headers.get('Location')})
    except AssertionError as error:
        errors.append(str(error))

if phase == 'default':
    request(path, 200, m['published'], canonical=path)
    request(path[:-1], 301, location=path)
    request('/choosing-a-vacation-rental/', 301, location=path)
    request('/?p=' + str(m['published']), 301, location=path)
    request('/?name=choosing-a-vacation-rental', 301, location=path)
    request(prefix + '/?p=' + str(m['published']), 404)
    request(path + 'extra/?p=' + str(m['published']), 404)
    for key in ['draft', 'private', 'future', 'pending']:
        request(prefix + '/fixture-investor-' + key + '/', 404)
        request('/?p=' + str(m[key]), 404)
    request('/?p=' + str(m['draft']) + '&preview=true', 404)
    for slug in ['no-such-article', 'choosing-a', 'fixture-old-investor', 'fixture-investor-ordinary', 'fixture-investor-guide-control', 'mauna-lani', 'fixture-community', 'fixture-investor-mixed', 'choosing-a-vacation-rental/extra']:
        request(prefix + '/' + slug + '/', 404)
    request('/big-island/guide/things-to-do/fixture-investor-mixed/', 404)
    request('/fixture-investor-mixed/', 404)
    request('/?p=' + str(m['mixed']), 404)
    request('/big-island/guide/things-to-do/fixture-investor-guide-control/', 200, m['guide'], canonical='/big-island/guide/things-to-do/fixture-investor-guide-control/')
    request('/fixture-investor-ordinary/', 200, m['ordinary'], canonical='/fixture-investor-ordinary/')
    for route, post_id in m['pages'].items():
        request('/' + route + '/', 200, post_id)
    request(prefix + '/', 404)
    request('/category/investor-guides/', 404)
    request('/category/investor-guides', 404)
    request('/category/investor-guides/feed/', 404)
    request('/?cat=' + str(m['category']), 404)
    request('/?category_name=investor-guides', 404)
elif phase == 'removed':
    request(path, 404)
    request('/choosing-a-vacation-rental/', 200, m['published'], canonical='/choosing-a-vacation-rental/')
    request('/big-island/guide/things-to-do/fixture-investor-guide-control/', 200, m['guide'])
elif phase in ['configured', 'restored']:
    request(path, 200, m['published'], canonical=path)
    request('/choosing-a-vacation-rental/', 301, location=path)
    old = '/homeowners/invest/guides' if phase == 'configured' else '/owners/research/guides'
    request(old + '/choosing-a-vacation-rental/', 404)
    for route, post_id in m['pages'].items():
        request('/' + route + '/', 200, post_id)
else:
    raise AssertionError(phase)
Path(sys.argv[1]).with_name('investor-http-' + phase + '-report.json').write_text(json.dumps(report, indent=2))
assert not errors, errors
print(f'PASS: {len(report)} Investor HTTP checks ({phase}).')
