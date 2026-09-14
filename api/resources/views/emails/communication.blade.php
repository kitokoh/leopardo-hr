{{--
    Message de communication générique (issue #7346).

    Était un fragment nu (aucun en-tête ni pied de page) : le destinataire
    recevait un texte sans identité visuelle. Passé au layout canonique, avec
    lien de désinscription quand il est fourni (le layout l'affiche lui-même).
--}}
@extends('emails.layouts.base')

@section('heading', $subjectLine ?? config('mail.brand.name'))

@section('content')
    <p style="margin:0; white-space:pre-line;">{{ $bodyText }}</p>
@endsection
