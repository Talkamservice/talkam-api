@if(isset($items) && count($items) < 1)
<div class="intro-y box col-span-12">
    <div class="p-5 text-center">
        {{ $message ?? "No data found"}}
    </div>
</div>
@endif
