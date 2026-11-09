<?php
// hreflang, ?lang=, OG debat, JSON-LD dossier. Lancer : APP_ENV=test php tests/e2e/e2e_seo_hreflang.php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
$k = new App\Kernel('test', true); $k->boot();
$ok = 0; $ko = 0;
function t($n, $x) { global $ok, $ko; if ($x) { $ok++; echo "OK  $n\n"; } else { $ko++; echo "KO  $n\n"; } }
$b = new KernelBrowser($k); $b->disableReboot();
$b->request('GET', '/programmes'); $h = $b->getResponse()->getContent();
t('hreflang fr, en, ar presents', str_contains($h, 'hreflang="fr"') && str_contains($h, 'hreflang="en"') && str_contains($h, 'hreflang="ar"'));
t('x-default sans parametre', (bool) preg_match('~hreflang="x-default" href="[^"?]*/programmes"~', $h));
t('pas d\'hreflang pour une langue non traduite', !str_contains($h, 'rel="alternate" hreflang="nl"'));
$b->request('GET', '/programmes?lang=en'); $h = $b->getResponse()->getContent();
t('?lang=en : html lang=en', str_contains($h, '<html lang="en"'));
t('?lang=en : canonical auto-reference', (bool) preg_match('~rel="canonical" href="[^"]*/programmes\?lang=en"~', $h));
t('?lang=en ne pose pas de cookie de langue', !str_contains(implode(';', array_map('strval', $b->getResponse()->headers->getCookies())), 'lfed_lang'));
$b->request('GET', '/programmes?lang=nl'); t('langue non traduite ignoree', str_contains($b->getResponse()->getContent(), '<html lang="fr"') || !str_contains($b->getResponse()->getContent(), '<html lang="nl"'));
$b->request('GET', '/programmes?groupe=X'); t('page filtree : pas d\'hreflang', !str_contains($b->getResponse()->getContent(), 'rel="alternate" hreflang="en"'));
$b->request('GET', '/admin'); t('admin : pas d\'hreflang', !str_contains($b->getResponse()->getContent(), 'rel="alternate" hreflang='));
$b->request('GET', '/dossiers/petition'); $h = $b->getResponse()->getContent();
t('dossier : JSON-LD Article', str_contains($h, '"@type":"Article"') && str_contains($h, 'isAccessibleForFree'));
echo "$ok ok / $ko ko\n"; exit($ko ? 1 : 0);
