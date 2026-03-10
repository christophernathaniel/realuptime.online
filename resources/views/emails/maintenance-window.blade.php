@include('emails.partials.shell', [
    'subject' => $subject,
    'preheader' => $preheader,
    'eyebrow' => $eyebrow,
    'title' => $title,
    'intro' => $intro,
    'toneLabel' => $toneLabel,
    'details' => $details,
    'buttonLabel' => $buttonLabel,
    'buttonUrl' => $buttonUrl,
    'footnote' => $footnote,
])
