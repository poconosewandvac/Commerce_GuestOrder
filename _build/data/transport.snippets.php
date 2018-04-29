<?php

function getSnippetContent($filename) {
    $o = file_get_contents($filename);
    $o = str_replace('<?php','',$o);
    $o = str_replace('?>','',$o);
    $o = trim($o);
    return $o;
}

$snippets = array();
$snippets[0] = $modx->newObject('modSnippet');
$snippets[0]->fromArray(array(
    'id' => 0,
    'name' => 'GetGuestOrder',
    'description' => 'Web viewing of a guest customer\'s previous orders.',
    'snippet' => getSnippetContent($sources['source_core'].'/elements/snippets/snippet.guestorder.php'),
),'',true,true);

return $snippets;