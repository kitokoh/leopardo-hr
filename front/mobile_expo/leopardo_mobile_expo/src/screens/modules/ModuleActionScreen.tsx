import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { extractErrorMessage } from '../../api/client';
import { Button } from '../../components/Button';
import { Card } from '../../components/Card';
import { TextField } from '../../components/TextField';
import { colors, radius, spacing, typography } from '../../theme';
import type { ActionField, ActionValues, ModuleAction, RowAction } from '../../verticals/actions';

/**
 * Ecran GENERIQUE d'action d'ecriture.
 *
 * Rend un formulaire a partir de la description declarative de l'action
 * (`ModuleAction` ou `RowAction`) : champs texte, numeriques, montants,
 * dates, listes deroulantes alimentees par l'API, interrupteurs.
 *
 * Aucune logique metier ici : la construction du payload et l'appel API
 * sont portes par l'action elle-meme (`build` / `submit`), ce qui garantit
 * que le contrat envoye a l'API est celui du depot `leopardo-hr`.
 */

type Props = {
  title: string;
  subtitle?: string;
  /** Action de creation. */
  action?: ModuleAction;
  /** Action sur un element existant. */
  rowAction?: RowAction;
  onBack: () => void;
  onDone: () => void;
};

type Option = { value: string; label: string };

export function ModuleActionScreen({ title, subtitle, action, rowAction, onBack, onDone }: Props) {
  const fields = useMemo<ActionField[]>(
    () => action?.fields ?? rowAction?.fields ?? [],
    [action, rowAction],
  );

  const [values, setValues] = useState<ActionValues>(() => {
    const initial: ActionValues = {};
    for (const field of fields) {
      if (field.defaultValue !== undefined) initial[field.key] = field.defaultValue;
    }
    return initial;
  });

  const [options, setOptions] = useState<Record<string, Option[]>>({});
  const [optionsLoading, setOptionsLoading] = useState<Record<string, boolean>>({});
  const [targets, setTargets] = useState<{ id: number; label: string; meta?: string }[]>([]);
  const [targetId, setTargetId] = useState<number | null>(null);
  const [targetsLoading, setTargetsLoading] = useState(Boolean(rowAction));
  const [targetsError, setTargetsError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // --- Chargement des options des listes deroulantes ----------------------
  useEffect(() => {
    let cancelled = false;

    fields.forEach((field) => {
      if (field.type !== 'select' || !field.loadOptions) return;
      setOptionsLoading((prev) => ({ ...prev, [field.key]: true }));
      field
        .loadOptions()
        .then((loaded) => {
          if (cancelled) return;
          setOptions((prev) => ({ ...prev, [field.key]: loaded }));
        })
        .catch(() => {
          if (cancelled) return;
          setOptions((prev) => ({ ...prev, [field.key]: [] }));
        })
        .finally(() => {
          if (cancelled) return;
          setOptionsLoading((prev) => ({ ...prev, [field.key]: false }));
        });
    });

    return () => {
      cancelled = true;
    };
  }, [fields]);

  // --- Chargement des elements cibles (actions de ligne) ------------------
  useEffect(() => {
    if (!rowAction) return;
    let cancelled = false;
    setTargetsLoading(true);
    rowAction
      .loadTargets()
      .then((loaded) => {
        if (cancelled) return;
        setTargets(loaded);
        setTargetsError(null);
        if (loaded.length === 1) setTargetId(loaded[0].id);
      })
      .catch((err) => {
        if (cancelled) return;
        setTargets([]);
        setTargetsError(extractErrorMessage(err));
      })
      .finally(() => {
        if (cancelled) return;
        setTargetsLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [rowAction]);

  const setValue = useCallback((key: string, value: string) => {
    setValues((prev) => ({ ...prev, [key]: value }));
  }, []);

  const missingField = useMemo(() => {
    for (const field of fields) {
      if (!field.required) continue;
      const value = values[field.key];
      if (value === undefined || value.trim() === '') return field;
    }
    return null;
  }, [fields, values]);

  const needsTarget = Boolean(rowAction);
  const canSubmit =
    !submitting && !missingField && (!needsTarget || targetId !== null) && !targetsLoading;

  const runSubmit = useCallback(async () => {
    setSubmitting(true);
    setError(null);
    try {
      if (action) {
        const payload = action.build(values);
        await action.submit(payload);
        Alert.alert('Succes', action.successMessage, [{ text: 'OK', onPress: onDone }]);
      } else if (rowAction && targetId !== null) {
        const payload = rowAction.build(targetId, values);
        await rowAction.submit(targetId, payload);
        Alert.alert('Succes', rowAction.successMessage, [{ text: 'OK', onPress: onDone }]);
      }
    } catch (err) {
      setError(extractErrorMessage(err));
    } finally {
      setSubmitting(false);
    }
  }, [action, rowAction, values, targetId, onDone]);

  const handleSubmit = useCallback(() => {
    const destructive = action?.destructive ?? rowAction?.destructive ?? false;
    if (!destructive) {
      runSubmit();
      return;
    }
    Alert.alert(
      'Confirmer',
      'Cette operation est irreversible. Voulez-vous continuer ?',
      [
        { text: 'Annuler', style: 'cancel' },
        { text: 'Confirmer', style: 'destructive', onPress: runSubmit },
      ],
    );
  }, [action, rowAction, runSubmit]);

  return (
    <SafeAreaView style={styles.safe} edges={['top']}>
      <View style={styles.header}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Retour"
          onPress={onBack}
          style={styles.backButton}
          hitSlop={12}
        >
          <Text style={styles.backGlyph}>{'\u2039'}</Text>
        </Pressable>
        <View style={styles.headerTexts}>
          <Text style={styles.title} numberOfLines={1}>
            {title}
          </Text>
          {subtitle ? (
            <Text style={styles.subtitle} numberOfLines={2}>
              {subtitle}
            </Text>
          ) : null}
        </View>
      </View>

      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          {/* Selection de l'element cible */}
          {needsTarget ? (
            <View style={styles.block}>
              <Text style={styles.blockTitle}>Element concerne</Text>
              {targetsLoading ? (
                <Card>
                  <View style={styles.loadingRow}>
                    <ActivityIndicator color={colors.primary} />
                    <Text style={styles.loadingText}>Chargement…</Text>
                  </View>
                </Card>
              ) : targetsError ? (
                <Card style={styles.errorCard}>
                  <Text style={styles.errorText}>{targetsError}</Text>
                </Card>
              ) : targets.length === 0 ? (
                <Card>
                  <Text style={styles.emptyText}>
                    Aucun element disponible pour cette action.
                  </Text>
                </Card>
              ) : (
                <View style={styles.optionList}>
                  {targets.slice(0, 40).map((target) => {
                    const selected = targetId === target.id;
                    return (
                      <Pressable
                        key={target.id}
                        accessibilityRole="radio"
                        accessibilityState={{ selected }}
                        accessibilityLabel={target.label}
                        onPress={() => setTargetId(target.id)}
                        style={({ pressed }) => [
                          styles.optionRow,
                          selected && styles.optionRowSelected,
                          pressed && styles.pressed,
                        ]}
                      >
                        <View style={[styles.radio, selected && styles.radioSelected]}>
                          {selected ? <View style={styles.radioDot} /> : null}
                        </View>
                        <View style={styles.optionTexts}>
                          <Text style={styles.optionLabel} numberOfLines={1}>
                            {target.label}
                          </Text>
                          {target.meta ? (
                            <Text style={styles.optionMeta} numberOfLines={1}>
                              {target.meta}
                            </Text>
                          ) : null}
                        </View>
                      </Pressable>
                    );
                  })}
                </View>
              )}
            </View>
          ) : null}

          {/* Champs du formulaire */}
          {fields.map((field) => (
            <FieldRenderer
              key={field.key}
              field={field}
              value={values[field.key] ?? ''}
              options={options[field.key] ?? field.options ?? []}
              loading={optionsLoading[field.key] ?? false}
              onChange={(value) => setValue(field.key, value)}
            />
          ))}

          {error ? (
            <Card style={styles.errorCard}>
              <Text style={styles.errorText}>{error}</Text>
            </Card>
          ) : null}

          {missingField ? (
            <Text style={styles.hint}>
              Champ requis : {missingField.label}
            </Text>
          ) : null}

          <Button
            label={submitting ? 'Envoi…' : 'Valider'}
            onPress={handleSubmit}
            loading={submitting}
            disabled={!canSubmit}
            style={styles.submit}
          />
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Rendu d'un champ
// ─────────────────────────────────────────────────────────────────────────────

function FieldRenderer({
  field,
  value,
  options,
  loading,
  onChange,
}: {
  field: ActionField;
  value: string;
  options: Option[];
  loading: boolean;
  onChange: (value: string) => void;
}) {
  if (field.type === 'switch') {
    return (
      <View style={styles.switchRow}>
        <View style={styles.switchTexts}>
          <Text style={styles.switchLabel}>{field.label}</Text>
          {field.help ? <Text style={styles.switchHelp}>{field.help}</Text> : null}
        </View>
        <Switch
          accessibilityLabel={field.label}
          value={value === 'true'}
          onValueChange={(next) => onChange(next ? 'true' : 'false')}
          trackColor={{ false: colors.border, true: `${colors.primary}88` }}
          thumbColor={value === 'true' ? colors.primary : colors.textFaint}
        />
      </View>
    );
  }

  if (field.type === 'select') {
    return (
      <View style={styles.selectWrapper}>
        <Text style={styles.selectLabel}>
          {field.label}
          {field.required ? <Text style={styles.required}> *</Text> : null}
        </Text>
        {field.help ? <Text style={styles.selectHelp}>{field.help}</Text> : null}
        {loading ? (
          <View style={styles.loadingRow}>
            <ActivityIndicator color={colors.primary} size="small" />
            <Text style={styles.loadingText}>Chargement des options…</Text>
          </View>
        ) : options.length === 0 ? (
          <Text style={styles.emptyOptions}>Aucune option disponible.</Text>
        ) : (
          <View style={styles.optionList}>
            {options.map((option) => {
              const selected = value === option.value;
              return (
                <Pressable
                  key={option.value}
                  accessibilityRole="radio"
                  accessibilityState={{ selected }}
                  accessibilityLabel={option.label}
                  onPress={() => onChange(option.value)}
                  style={({ pressed }) => [
                    styles.optionRow,
                    selected && styles.optionRowSelected,
                    pressed && styles.pressed,
                  ]}
                >
                  <View style={[styles.radio, selected && styles.radioSelected]}>
                    {selected ? <View style={styles.radioDot} /> : null}
                  </View>
                  <Text style={styles.optionLabel} numberOfLines={2}>
                    {option.label}
                  </Text>
                </Pressable>
              );
            })}
          </View>
        )}
      </View>
    );
  }

  const keyboardType =
    field.type === 'number' || field.type === 'money' ? 'decimal-pad' : 'default';

  return (
    <TextField
      label={`${field.label}${field.required ? ' *' : ''}`}
      value={value}
      onChangeText={onChange}
      placeholder={field.placeholder}
      keyboardType={keyboardType}
      autoCapitalize={field.type === 'text' ? 'sentences' : 'none'}
      multiline={field.type === 'multiline'}
      numberOfLines={field.type === 'multiline' ? 4 : 1}
      style={field.type === 'multiline' ? styles.multiline : undefined}
    />
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  flex: { flex: 1 },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    paddingBottom: spacing.md,
    gap: spacing.md,
  },
  backButton: {
    width: 36,
    height: 36,
    borderRadius: radius.md,
    backgroundColor: colors.surface,
    borderWidth: 1,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  backGlyph: { color: colors.text, fontSize: 24, lineHeight: 26, marginTop: -2 },
  headerTexts: { flex: 1 },
  title: { color: colors.text, fontSize: typography.heading, fontWeight: '700', letterSpacing: -0.4 },
  subtitle: { color: colors.textMuted, fontSize: typography.caption, marginTop: 2, lineHeight: 16 },
  content: { paddingHorizontal: spacing.lg, paddingBottom: spacing.xxl },
  block: { marginBottom: spacing.lg },
  blockTitle: {
    color: colors.textMuted,
    fontSize: typography.caption,
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginBottom: spacing.sm,
  },
  selectWrapper: { marginBottom: spacing.lg },
  selectLabel: {
    color: colors.textMuted,
    fontSize: typography.caption,
    fontWeight: '600',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginBottom: spacing.xs,
  },
  selectHelp: { color: colors.textFaint, fontSize: typography.caption, marginBottom: spacing.sm },
  required: { color: colors.danger },
  optionList: {
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    overflow: 'hidden',
  },
  optionRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    gap: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.border,
  },
  optionRowSelected: { backgroundColor: `${colors.primary}18` },
  pressed: { opacity: 0.75 },
  radio: {
    width: 18,
    height: 18,
    borderRadius: 9,
    borderWidth: 2,
    borderColor: colors.border,
    alignItems: 'center',
    justifyContent: 'center',
  },
  radioSelected: { borderColor: colors.primary },
  radioDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.primary },
  optionTexts: { flex: 1 },
  optionLabel: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  optionMeta: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  loadingRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, paddingVertical: spacing.sm },
  loadingText: { color: colors.textMuted, fontSize: typography.caption },
  emptyText: { color: colors.textMuted, fontSize: typography.body },
  emptyOptions: { color: colors.textFaint, fontSize: typography.caption, paddingVertical: spacing.sm },
  switchRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: colors.surface,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.border,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    marginBottom: spacing.lg,
    gap: spacing.md,
  },
  switchTexts: { flex: 1 },
  switchLabel: { color: colors.text, fontSize: typography.body, fontWeight: '600' },
  switchHelp: { color: colors.textFaint, fontSize: typography.caption, marginTop: 2 },
  multiline: { minHeight: 96, textAlignVertical: 'top' },
  errorCard: { borderColor: `${colors.danger}66`, marginBottom: spacing.lg },
  errorText: { color: colors.danger, fontSize: typography.caption, lineHeight: 18 },
  hint: { color: colors.warning, fontSize: typography.caption, marginBottom: spacing.md },
  submit: { marginTop: spacing.sm },
});
