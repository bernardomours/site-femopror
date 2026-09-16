@props(['titulo' => 'FEMOPROR', 'previa' => ''])

@include('emails.layout', ['titulo' => $titulo, 'previa' => $previa, 'slot' => $slot])
