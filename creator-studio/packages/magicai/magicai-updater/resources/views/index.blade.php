@extends('magicai-updater::layouts.master', ['stepShow' => false])

@section('template_title')
    {{ $data['title'] }}
@endsection

@section('title')
    {{ $data['title'] }}
@endsection

@section('container')
    @php
        $steps_indicators = ['Updater', 'Check Requirement', 'Backup', 'Download', 'Upgrade'];
        $step = data_get($data, 'step');
    @endphp

    @if ($step)
        <div class="mb-5 flex flex-col border-t first:border-t-0">
            <div class="flex justify-between gap-4 overflow-x-auto max-lg:hidden">
                @foreach ($steps_indicators as $step_indicator)
                    <div @class([
                        'flex justify-center flex-col text-center items-center gap-3 p-4 text-sm font-semibold',
                        'text-foreground/25' => $loop->index + 1 > $step,
                    ])>
                        <span @class([
                            'size-9 inline-grid shrink-0 place-content-center rounded-full',
                            'bg-primary/10 text-primary' => $loop->index + 1 <= $step,
                            'border border-foreground/10 text-foreground' => $loop->index + 1 > $step,
                        ])>
                            {{ $loop->index + 1 }}
                        </span>
                        {{ __($step_indicator) }}
                    </div>
                    @if (!$loop->last)
                        <x-tabler-chevron-right class="mt-[26px] size-4 max-lg:hidden" />
                    @endif
                @endforeach
            </div>
            <div class="lqd-progress relative h-1.5 w-full bg-foreground/10">
                <div
                    class="lqd-progress-bar absolute inset-0 rounded-full bg-gradient-to-br from-[#82E2F4] to-[#8A8AED]"
                    style="width: {{ ($step / count($steps_indicators)) * 100 }}%"
                ></div>
            </div>
        </div>
    @endif

    @include($data['view'])
@endsection

@section('scripts')
    <script>
        @php
            $text = match ($step) {
                1 => 'Checking Requirement...',
                2 => 'Backing up...',
                3 => 'Downloading...',
                4 => 'Upgrading...',
                default => 'Loading...',
            };

        @endphp

        $(document).ready(function() {
            $('#submit-button').on('click', function() {
                $(this).attr('disabled', true);
                $(this).text('{{ $text }}');
                $(this).closest('form').submit();
            });
        });
    </script>
@endsection
