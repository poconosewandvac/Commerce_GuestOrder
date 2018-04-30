<?php

function getChunkContent($filename) {
    $o = file_get_contents($filename);
    return $o;
}

$chunks = array();
$chunks[0] = $modx->newObject('modChunk');
$chunks[0]->fromArray(array(
    'id' => 0,
    'name' => 'GetGuestOrderForm',
    'description' => 'Default form of guest order checker with zip code verification.',
    'snippet' => getChunkContent($sources['source_core'].'/elements/chunks/chunk.guestorderform.tpl'),
),'',true,true);

$chunks[1] = $modx->newObject('modChunk');
$chunks[1]->fromArray(array(
    'id' => 0,
    'name' => 'GetGuestOrderError',
    'description' => 'Default error form for guest order verification.',
    'snippet' => getChunkContent($sources['source_core'].'/elements/chunks/chunk.guestordererror.tpl'),
),'',true,true);

return $chunks;