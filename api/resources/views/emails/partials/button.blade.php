{{--
    Bouton d’appel à l’action (issue #7346).

    Bouton « bulletproof » en TABLE avec `bgcolor` + styles inline : c’est la
    seule forme qui survit à Gmail, Outlook (Word) et Apple Mail. Avant, trois
    mécanismes coexistaient (classe CSS dans un `<style>` supprimé par Gmail,
    styles inline ad hoc, composant `mail::button` au thème Laravel par défaut).

    Variables attendues :
      $url    – cible (obligatoire)
      $label  – libellé (obligatoire, déjà traduit par l’appelant)
      $align  – 'left' | 'center' | 'right' (défaut : hérite du contexte)
--}}
@php
    $buttonAlign = $align ?? 'left';
    $buttonPrimary = config('mail.brand.primary_color', '#0d9488');
    $buttonRadius = '8px';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="{{ $buttonAlign }}" style="margin:20px 0;">
    <tr>
        <td align="center" bgcolor="{{ $buttonPrimary }}" style="border-radius:{{ $buttonRadius }}; background-color:{{ $buttonPrimary }};">
            <a href="{{ $url }}"
               target="_blank"
               rel="noopener"
               style="display:inline-block; padding:13px 26px; font-family:'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:15px; font-weight:600; line-height:20px; color:#ffffff; text-decoration:none; border-radius:{{ $buttonRadius }}; background-color:{{ $buttonPrimary }};">
                {{ $label }}
            </a>
        </td>
    </tr>
</table>
