@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 focus:border-green-800 focus:ring-green-800 rounded-md shadow-sm']) !!}>