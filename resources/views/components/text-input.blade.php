@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-gray-300 focus:border-mochi-header focus:ring-mochi-header rounded-md shadow-sm']) }}>
