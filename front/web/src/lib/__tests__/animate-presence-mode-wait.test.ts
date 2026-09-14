/**
 * @jest-environment node
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

import ts from 'typescript';

/**
 * Issue #7305 — garde `<AnimatePresence mode="wait">` multi-enfants.
 *
 * framer-motion n'accepte qu'UN enfant animé par passe en `mode="wait"` :
 *   « You're attempting to animate multiple children within AnimatePresence,
 *     but its mode is set to "wait". This will lead to odd visual behaviour. »
 * Le cas réel trouvé dans le funnel est une LISTE mappée glissée dans une
 * `AnimatePresence mode="wait"` (`filteredFaq.map(...)` sur `/pricing`, page
 * cible de la redirection `/signup` sans `?plan=`) : N enfants rendus dans la
 * même passe ⇒ avertissement console à chaque affichage.
 *
 * Cette garde lit l'AST TypeScript (pas une regex) : pour chaque
 * `AnimatePresence` portant `mode="wait"`, elle refuse un enfant DIRECT dont
 * l'expression est un `xxx.map(...)` (liste = plusieurs enfants certains).
 * Les enfants conditionnels uniques (`{step === 'x' && <motion.div/>}`) restent
 * légitimes : c'est l'usage prévu du mode `wait` (transitions d'étapes).
 */

const WEB_ROOT = join(__dirname, '..', '..', '..');
const SRC_ROOT = join(WEB_ROOT, 'src');

function listTsx(dir: string, acc: string[] = []): string[] {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      if (entry === '__tests__' || entry === 'node_modules') continue;
      listTsx(full, acc);
    } else if (entry.endsWith('.tsx')) {
      acc.push(full);
    }
  }
  return acc;
}

type Violation = { file: string; line: number; text: string };

function isMapCall(node: ts.Expression): boolean {
  let expr: ts.Expression = node;
  while (ts.isParenthesizedExpression(expr)) expr = expr.expression;
  return (
    ts.isCallExpression(expr) &&
    ts.isPropertyAccessExpression(expr.expression) &&
    expr.expression.name.text === 'map'
  );
}

function hasWaitMode(node: ts.JsxOpeningLikeElement): boolean {
  return node.attributes.properties.some(
    (attr) =>
      ts.isJsxAttribute(attr) &&
      attr.name.getText() === 'mode' &&
      attr.initializer !== undefined &&
      ts.isStringLiteral(attr.initializer) &&
      attr.initializer.text === 'wait',
  );
}

function scan(source: string, fileName: string, violations: Violation[], usages: Violation[]): void {
  const sf = ts.createSourceFile(fileName, source, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);

  const visit = (node: ts.Node): void => {
    if (ts.isJsxElement(node) && ts.isIdentifier(node.openingElement.tagName)) {
      if (node.openingElement.tagName.text === 'AnimatePresence' && hasWaitMode(node.openingElement)) {
        const { line } = sf.getLineAndCharacterOfPosition(node.getStart(sf));
        usages.push({ file: fileName, line: line + 1, text: 'AnimatePresence mode="wait"' });
        for (const child of node.children) {
          if (!ts.isJsxExpression(child) || !child.expression) continue;
          if (isMapCall(child.expression)) {
            const childLine = sf.getLineAndCharacterOfPosition(child.getStart(sf)).line;
            violations.push({
              file: fileName,
              line: childLine + 1,
              text: child.getText(sf).split('\n')[0].trim(),
            });
          }
        }
      }
    }
    ts.forEachChild(node, visit);
  };

  visit(sf);
}

describe('AnimatePresence mode="wait" — un seul enfant animé par passe (#7305)', () => {
  const violations: Violation[] = [];
  const usages: Violation[] = [];

  for (const file of listTsx(SRC_ROOT).sort()) {
    const rel = file.replace(`${WEB_ROOT}/`, '');
    scan(readFileSync(file, 'utf8'), rel, violations, usages);
  }

  it('aucune `AnimatePresence mode="wait"` ne reçoit une liste mappée', () => {
    expect(
      violations.map((v) => `${v.file}:${v.line} — ${v.text}`),
    ).toEqual([]);
  });

  it('la garde voit bien les usages légitimes (test non vacuous)', () => {
    // Les transitions d'étapes du funnel (`SignupForm`, `checkout`,
    // `RestaurantSolutionWizard`) doivent rester détectées : un scanner cassé
    // qui ne trouve plus rien ne doit pas passer pour un succès.
    expect(usages.length).toBeGreaterThanOrEqual(3);
  });
});
