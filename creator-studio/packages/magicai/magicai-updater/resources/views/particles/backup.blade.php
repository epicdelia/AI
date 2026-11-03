<div class="mx-auto mt-6">
   <div class="flex flex-col mb-4 ">
       <div class="flex justify-center">
           <x-tabler-file-zip class="size-16 text-green-600" />

       </div>

         <p class="text-sm font-bold ml-2">
             Great! Your system is backed up successfully.
         </p>
         <small class="mt-2 p-2 border rounded bg-blue-200">
             {{ $fileName }}
         </small>
   </div>

    <form
        id="upgrade-form"
        action="{{ route('updater.download') }}"
        method="POST"
    >
        @csrf
        <x-updater-button
            id="update-button"
            :permission="extension_loaded('zip')"
            :text="$permission ? __('Download ') . $data['version'].'\'version' : __('Upgrade ') . $data['version'].'\'version'"
        />
    </form>
</div>

@push('extra_script')
    <script>

        $('#upgrade-form').on('submit', function(event) {
            $('#update-button').attr('disabled', true);
            $('#update-button').text('Downloading ...');
        });
    </script>
@endpush
