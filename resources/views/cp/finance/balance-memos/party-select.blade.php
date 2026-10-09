<select name="{{ $name }}" required class="w-full rounded-xl border px-3 py-2 dark:bg-slate-700" @disabled(!empty($locked))>
    <option value="">اختر الجهة</option>
    @if(($parties['clients'] ?? collect())->isNotEmpty())
        <optgroup label="الزبائن">
            @foreach($parties['clients'] as $c)
                <option value="client:{{ $c->id }}" @selected($selected === 'client:'.$c->id)>{{ $c->directoryLabel() }}</option>
            @endforeach
        </optgroup>
    @endif
    @if(($parties['persons'] ?? collect())->isNotEmpty())
        <optgroup label="الأشخاص">
            @foreach($parties['persons'] as $p)
                <option value="person:{{ $p->id }}" @selected($selected === 'person:'.$p->id)>{{ $p->name }}</option>
            @endforeach
        </optgroup>
    @endif
    @if($workers->isNotEmpty())
        <optgroup label="الموظفون">
            @foreach($workers as $w)
                <option value="vendor:{{ $w->id }}" @selected($selected === 'vendor:'.$w->id)>{{ $w->name }}</option>
            @endforeach
        </optgroup>
    @endif
    @if($suppliers->isNotEmpty())
        <optgroup label="الموردون">
            @foreach($suppliers as $s)
                <option value="vendor:{{ $s->id }}" @selected($selected === 'vendor:'.$s->id)>{{ $s->name }}</option>
            @endforeach
        </optgroup>
    @endif
</select>
@if(!empty($locked))
    <input type="hidden" name="{{ $name }}" value="{{ $selected }}">
@endif
