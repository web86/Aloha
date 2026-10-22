#!/usr/bin/env python3
"""Guide route/canonical assertions, disposable site only, standard library."""
import json
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

manifest = json.loads(Path(sys.argv[1]).read_text())
base = manifest['base'].rstrip('/')
assert base == 'http://127.0.0.1:8091', 'Refuse the main database'
phase = sys.argv[2] if len(sys.argv) > 2 else 'default'
prefix = '/island-test/guide' if phase == 'configured' else '/big-island/travel' if phase == 'segment' else '/big-island/guide'
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None
opener = urllib.request.build_opener(NoRedirect())
report, errors = [], []

def request(path, status, post_id=None, taxonomy=None, canonical=None, location=None, allowed_posts=None):
    try:
        try:
            result = opener.open(base + path, timeout=20)
        except urllib.error.HTTPError as error:
            result = error
        body = result.read().decode('utf-8')
        assert result.code in ((status,) if isinstance(status, int) else status), (path, result.code, status)
        if post_id is not None:
            assert result.headers.get('X-Aloha-Test-Object-ID') == str(post_id), (path, result.headers)
        if taxonomy:
            assert result.headers.get('X-Aloha-Test-Taxonomy') == taxonomy, path
        if result.code == 404:
            assert result.headers.get('X-Aloha-Test-Is-404') == '1', path
        if canonical:
            assert re.search(r'<link\b(?=[^>]*rel=[\"\']canonical[\"\'])(?=[^>]*href=[\"\']' + re.escape(base + canonical) + r'[\"\'])[^>]*>', body), (path, 'canonical')
        if location:
            assert result.headers.get('Location') == base + location, (path, result.headers.get('Location'))
        if allowed_posts is not None:
            ids = [int(x) for x in result.headers.get('X-Aloha-Test-Posts', '').split(',') if x]
            assert ids and set(ids) <= set(allowed_posts), (path, ids, allowed_posts)
        report.append({'path': path, 'status': result.code, 'id': result.headers.get('X-Aloha-Test-Object-ID'), 'taxonomy': result.headers.get('X-Aloha-Test-Taxonomy'), 'location': result.headers.get('Location')})
    except AssertionError as error:
        errors.append(str(error))

for slug, post_id in manifest['articles'].items():
    path = prefix + '/' + slug + '/fixture-guide-' + slug + '/'
    request(path, 200, post_id, canonical=path)
    archive = prefix + '/' + slug + '/'
    request(archive, 200, manifest['map']['rubrics'][slug], 'category', canonical=archive)
if phase == 'default':
    for slug, post_id in manifest['articles'].items():
        path = prefix + '/' + slug + '/fixture-guide-' + slug + '/'
        archive = prefix + '/' + slug + '/'
        request(path[:-1], 301, location=path)
        request(archive[:-1], 301, location=archive)
        request('/fixture-guide-' + slug + '/', 301, location=path)
        request('/?p=' + str(post_id), 301, location=path)
        request('/category/big-island-guide/' + slug + '/', 301, location=archive)
        request('/?cat=' + str(manifest['map']['rubrics'][slug]), 301, location=archive)
    request('/category/big-island-guide/', 301, location=prefix + '/')
    request('/?cat=' + str(manifest['map']['parent']), 301, location=prefix + '/')
    request(prefix + '/', 200, manifest['hub'])
    for suffix in ['food-and-drink/fixture-guide-things-to-do/', 'not-a-rubric/fixture-guide-things-to-do/', 'things-to-do/fixture-guide-things/', 'things-to-do/missing-post/', 'things-to-do/fixture-guide-draft/', 'things-to-do/fixture-guide-private/', 'things-to-do/fixture-guide-future/', 'things-to-do/fixture-guide-ambiguous/', 'events/fixture-guide-ambiguous/', 'things-to-do/fixture-ordinary-post/', 'things-to-do/fixture-guide-things-to-do/embed/', 'big-island-guide/things-to-do/fixture-guide-things-to-do/']:
        request(prefix + '/' + suffix, 404)
    for key in ['draft', 'private', 'future', 'ambiguous']:
        request('/?p=' + str(manifest[key]), 404)
    request('/?p=' + str(manifest['draft']) + '&preview=true', 404)
    request('/fixture-ordinary-post/', 200, manifest['ordinary'], canonical='/fixture-ordinary-post/')
    request('/category/fixture-general/', 200, manifest['ordinary_category'], 'category')
    things = [manifest['articles']['things-to-do'], manifest['descendant'], manifest['extra']]
    request(prefix + '/things-to-do/', 200, manifest['map']['rubrics']['things-to-do'], 'category', allowed_posts=things)
    request(prefix + '/things-to-do/page/2/', 200, manifest['map']['rubrics']['things-to-do'], 'category', canonical=prefix + '/things-to-do/page/2/', allowed_posts=things)
    request('/category/big-island-guide/things-to-do/page/2/', 301, location=prefix + '/things-to-do/page/2/')
    request('/category/big-island-guide/things-to-do/fixture-guide-detail/', 301, location=prefix + '/things-to-do/')
    request(prefix + '/events/page/999/', 404)
elif phase in ['configured', 'segment']:
    request('/big-island/guide/things-to-do/fixture-guide-things-to-do/', 404)
    request('/big-island/guide/', 200, manifest['hub'])
    request('/fixture-guide-things-to-do/', 301, location=prefix + '/things-to-do/fixture-guide-things-to-do/')
elif phase == 'restored':
    request('/island-test/guide/things-to-do/fixture-guide-things-to-do/', 404)
    request('/big-island/travel/things-to-do/fixture-guide-things-to-do/', 404)
else:
    raise AssertionError(phase)
Path(sys.argv[1]).with_name('guide-http-' + phase + '-report.json').write_text(json.dumps(report, indent=2))
assert not errors, errors
print(f'PASS: {len(report)} Guide HTTP checks ({phase}).')
