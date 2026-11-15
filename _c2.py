import xml.etree.ElementTree as ET, re, collections
t = ET.parse('_a3/test-results/feature.xml')
fails = []
for tc in t.iter('testcase'):
    node = tc.find('failure')
    if node is None:
        node = tc.find('error')
    if node is None:
        continue
    txt = (node.text or node.get('message') or '')
    first = txt.strip().splitlines()[0][:150] if txt.strip() else '(vide)'
    frames = re.findall(r'(api/(?:app|tests)/[^:\s]+):(\d+)', txt)
    fails.append((tc.get('classname','').split('.')[-1], tc.get('name','')[:40], first, frames[0] if frames else ('?','?')))
print('echecs:', len(fails))
grp = collections.Counter((f[3][0], f[3][1], re.sub(r'\d+', 'N', f[2])[:90]) for f in fails)
print()
for (path, line, msg), n in grp.most_common(12):
    print('%2d x %s:%s\n     %s' % (n, path, line, msg))
