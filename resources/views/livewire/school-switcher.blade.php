<select wire:change="changeSchool($event.target.value)">
    @foreach(App\Services\SchoolService::all() as $school)
        <option
            value="{{ $school->id }}"
            @selected($school->id == App\Services\SchoolService::currentId())
        >
            {{ $school->name }}
        </option>
    @endforeach
</select>