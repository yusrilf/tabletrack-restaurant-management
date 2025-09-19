<div>
    <div
        class="mx-4 p-4 mb-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:border-gray-700 sm:p-6 dark:bg-gray-800">

        <h3 class="mb-4 text-xl font-semibold dark:text-white inline-flex gap-4 items-center">@lang('modules.settings.pushNotificationSettings')

        </h3>

        <form wire:submit="submitForm">
            <div class="grid gap-6">

                <!-- Pusher Beams Enable Switch with Info -->
                <div class="mb-4 p-4 border border-gray-200 dark:border-gray-700 rounded bg-gray-50 dark:bg-gray-800 gap-4">
                    <div class="flex items-start gap-4">
                        <x-checkbox name="beamerStatus" id="beamerStatus" wire:model.live='beamerStatus' class="mt-1" />
                        <div class="w-full" for="beamerStatus">
                            <div class="font-semibold text-gray-900 dark:text-white flex items-center justify-between"> @lang('superadmin.pusherBeams')
                                <div class="flex items-center justify-between gap-2 mx-2">
                                    <img src='{{ asset('img/Beams logo primary.png') }}' class='h-4 dark:mix-blend-plus-lighter' />
                                    <a href='https://pusher.com/tutorials/getting-started-pusher-beams/' target='_blank' class='text-sm font-medium inline-flex gap-1'>@lang('app.generateCredentials')
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/>
                                            <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">@lang('superadmin.pusherBeamsDescription')</div>
                        </div>
                    </div>
                    @if ($beamerStatus)
                        <!-- Pusher Beams (Browser Push Notification) Settings -->
                        <div class="mb-4 mt-4">
                            <div>
                                <x-label for="instanceID" value="Pusher Instance ID" />
                                <x-input id="instanceID" class="block mt-1 w-full" type="text" wire:model='instanceID' />
                                <x-input-error for="instanceID" class="mt-2" />
                            </div>
                            <div class="mt-3">
                                <x-label for="beamSecret" value="Pusher Beam Secret" />
                                <x-input-password id="beamSecret" class="block mt-1 w-full" wire:model='beamSecret' />
                                <x-input-error for="beamSecret" class="mt-2" />
                            </div>
                        </div>
                    @endif
                </div>



                <!-- Pusher Channels Enable Switch with Info -->
                <div class="mb-4 p-4 border border-gray-200 dark:border-gray-700 rounded bg-gray-50 dark:bg-gray-800  gap-4">
                    <div class="flex items-start gap-4">
                        <x-checkbox name="pusherBroadcastStatus" id="pusherBroadcastStatus" wire:model.live='pusherBroadcastStatus' class="mt-1" />
                        <div class="w-full" for="beamerStatus">
                            <div class="font-semibold text-gray-900 dark:text-white flex items-center justify-between">@lang('superadmin.pusherChannels')
                                <div class="flex items-center justify-between gap-2 mx-2">
                                    <svg width="60" height="16" viewBox="0 0 121 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="db" role="img" aria-labelledby="svg-559c203e">
                                        <g>
                                        <path d="M10.3263 31.9489V23.9287C10.3263 23.9085 10.3364 23.8984 10.3464 23.8883L20.6225 17.9363C20.6325 17.9262 20.6426 17.9161 20.6426 17.8959V15.616C20.6426 15.5857 20.6225 15.5655 20.5923 15.5655C20.5823 15.5655 20.5722 15.5655 20.5722 15.5756L10.3967 21.4773C10.3766 21.4873 10.3464 21.4873 10.3364 21.4571C10.3364 21.447 10.3263 21.4369 10.3263 21.4369V19.1569C10.3263 19.1368 10.3364 19.1267 10.3464 19.1166L20.6225 13.1645C20.6325 13.1544 20.6426 13.1443 20.6426 13.1242V10.8442C20.6426 10.8139 20.6225 10.7938 20.5923 10.7938C20.5823 10.7938 20.5722 10.7938 20.5722 10.8039L10.3967 16.6954C10.3766 16.7055 10.3464 16.7055 10.3364 16.6752C10.3364 16.6651 10.3263 16.6551 10.3263 16.6551V14.3751C10.3263 14.3549 10.3364 14.3448 10.3464 14.3348L20.6225 8.38268C20.6325 8.37259 20.6426 8.3625 20.6426 8.34232V6.02202C20.6426 5.99176 20.6225 5.96149 20.5923 5.94131L10.3665 0.00941036C10.3364 -0.0107662 10.3062 -0.0107662 10.2761 0.00941036L8.32541 1.1393C8.3053 1.14939 8.29525 1.17965 8.3053 1.19983C8.3053 1.20991 8.31536 1.20991 8.32541 1.22L18.5009 7.11155C18.521 7.12164 18.5311 7.15191 18.521 7.18217C18.521 7.19226 18.511 7.19226 18.5009 7.20235L16.5503 8.33223C16.5201 8.35241 16.4799 8.35241 16.4598 8.33223L6.24406 2.40033C6.21389 2.38015 6.17367 2.38015 6.14351 2.40033L4.20292 3.53022C4.18282 3.54031 4.17276 3.57057 4.18282 3.59075C4.18282 3.60083 4.19287 3.60083 4.20292 3.61092L14.3784 9.51256C14.3985 9.52265 14.4086 9.55292 14.3985 9.57309C14.3985 9.58318 14.3885 9.58318 14.3784 9.59327L12.4378 10.7232C12.4077 10.7433 12.3675 10.7433 12.3373 10.7232L2.11152 4.79125C2.08135 4.77107 2.04113 4.77107 2.01097 4.79125L0 5.96149V26.0271C0 26.0472 0.0100548 26.0573 0.0201097 26.0674L1.99086 27.2074C2.01097 27.2175 2.04113 27.2175 2.05119 27.1872C2.05119 27.1771 2.06124 27.167 2.06124 27.167V7.2427C2.06124 7.21244 2.08135 7.19226 2.11152 7.19226C2.12157 7.19226 2.13163 7.19226 2.13163 7.20235L4.10238 8.34232C4.11243 8.35241 4.12249 8.3625 4.12249 8.38268V28.418C4.12249 28.4382 4.13254 28.4482 4.1426 28.4583L6.11335 29.5983C6.13345 29.6084 6.16362 29.6084 6.18373 29.5781C6.18373 29.568 6.19378 29.558 6.19378 29.558V9.63362C6.19378 9.60336 6.21389 9.58318 6.24406 9.58318C6.25411 9.58318 6.26417 9.58318 6.26417 9.59327L8.23492 10.7332C8.24497 10.7433 8.25503 10.7534 8.25503 10.7736V30.8089C8.25503 30.8291 8.26508 30.8392 8.27514 30.8493L10.2459 31.9892C10.266 31.9993 10.2962 31.9892 10.3062 31.9691C10.3163 31.959 10.3263 31.959 10.3263 31.9489Z" fill="currentColor"></path>
                                        <path d="M30.9689 25.6343V6.32535C30.9689 6.12359 31.1298 5.96217 31.3209 5.96217H31.3309H37.0521C40.6819 5.96217 42.9342 8.08071 42.9342 11.6318C42.9342 15.1829 40.3803 17.4426 37.0219 17.4426H33.9653C33.8647 17.4426 33.7742 17.5233 33.7742 17.6343V25.6747C33.7742 25.8764 33.6134 26.0378 33.4223 26.0378H33.4123H31.3309C31.1298 26.0076 30.979 25.8361 30.9689 25.6343ZM37.0622 15.0517C38.9826 15.0517 40.0686 13.4477 40.0686 11.6318C40.0686 9.74528 39.0731 8.32283 37.0622 8.32283H33.9753C33.8748 8.32283 33.7943 8.41362 33.7843 8.51451V14.8701C33.7843 14.971 33.8748 15.0618 33.9753 15.0618L37.0622 15.0517Z" fill="currentColor"></path>
                                        <path d="M54.7788 5.93191H56.8601C57.0612 5.92182 57.2221 6.08323 57.2322 6.285V6.29508V20.8827C57.2322 24.1917 54.5274 26.2194 51.3702 26.2194C48.2733 26.2194 45.5685 24.1816 45.5685 20.8827V6.29508C45.5685 6.09332 45.7294 5.93191 45.9205 5.93191H45.9305H47.9817C48.1828 5.92182 48.3437 6.08323 48.3537 6.285V6.29508V20.8323C48.3537 22.6381 49.7312 23.7781 51.3601 23.7781C52.989 23.7781 54.3967 22.628 54.3967 20.8323V6.30517C54.4067 6.10341 54.5777 5.94199 54.7788 5.93191Z" fill="currentColor"></path>
                                        <path d="M64.3711 15.8588C62.3903 14.5271 61.0631 13.034 61.0631 10.7844C61.0631 7.5662 63.7678 5.70996 66.8346 5.70996C69.7304 5.70996 72.2642 7.32408 72.415 11.4098C72.415 11.6217 72.2541 11.7932 72.043 11.8033H70.1426C69.9516 11.8033 69.7907 11.652 69.7706 11.4603C69.6399 9.24086 68.3126 8.18159 66.6536 8.18159C65.0549 8.18159 63.8583 9.1198 63.8583 10.633C63.8583 11.9344 64.6426 12.6406 66.6837 14.0832L69.5494 16.1513C71.5302 17.5939 72.6463 18.8953 72.6463 20.9533C72.6463 24.2825 69.9113 26.2598 66.7038 26.2598C63.6673 26.2598 61.2742 24.5952 61.0128 20.5095C61.0027 20.3077 61.1536 20.1362 61.3547 20.116C61.3647 20.116 61.3748 20.116 61.3848 20.116H63.3154C63.5064 20.116 63.6673 20.2673 63.6874 20.459C63.8684 22.739 65.2258 23.7881 66.8245 23.7881C68.3629 23.7881 69.8007 22.9609 69.8007 21.0038C69.8007 19.7932 69.2578 19.198 67.6892 18.1488L64.3711 15.8588Z" fill="currentColor"></path>
                                        <path d="M86.0192 25.6343V17.1904C86.0192 17.0895 85.9287 16.9987 85.8282 16.9987H79.7048C79.6042 16.9987 79.5137 17.0794 79.5137 17.1904V25.6343C79.5137 25.8361 79.3528 25.9975 79.1618 25.9975H79.1517H77.0704C76.8693 26.0076 76.7084 25.8462 76.6984 25.6444V25.6343V6.32534C76.6984 6.12358 76.8592 5.96216 77.0503 5.96216H77.0603H79.1417C79.3428 5.95208 79.5037 6.11349 79.5137 6.31525V6.32534V14.396C79.5137 14.4968 79.6042 14.5876 79.7048 14.5876H85.8282C85.9287 14.5876 86.0192 14.5069 86.0192 14.396V6.32534C86.0192 6.12358 86.1801 5.96216 86.3711 5.96216H86.3812H88.4625C88.6636 5.95208 88.8245 6.11349 88.8346 6.31525V6.32534V25.6545C88.8346 25.8562 88.6737 26.0177 88.4826 26.0177H88.4726H86.3912C86.1901 26.0076 86.0192 25.8462 86.0192 25.6343Z" fill="currentColor"></path>
                                        <path d="M94.0932 25.6343V6.32534C94.0932 6.12358 94.2541 5.96216 94.4452 5.96216H94.4552H104.621C104.822 5.95208 104.983 6.11349 104.993 6.31525V6.32534V8C104.993 8.20176 104.832 8.36317 104.641 8.36317H104.631H97.0996C96.9991 8.36317 96.9086 8.44388 96.9086 8.55485V14.4767C96.9086 14.5775 96.9991 14.6683 97.0996 14.6683H102.278C102.479 14.6683 102.64 14.8197 102.64 15.0214V15.0315V16.7062C102.64 16.9079 102.479 17.0694 102.288 17.0694H102.278H97.0996C96.9991 17.0694 96.9086 17.1501 96.9086 17.261V23.4149C96.9086 23.5158 96.9991 23.6066 97.0996 23.6066H104.631C104.832 23.6066 104.993 23.768 105.003 23.9697V25.6444C105.003 25.8462 104.832 26.0076 104.631 26.0076H94.4653C94.2742 26.0177 94.1133 25.8663 94.1033 25.6747C94.0932 25.6545 94.0932 25.6444 94.0932 25.6343Z" fill="currentColor"></path>
                                        <path d="M117.974 25.6343L114.907 17.2308C114.877 17.1602 114.806 17.1097 114.726 17.1097H111.88C111.78 17.1097 111.689 17.1904 111.689 17.3014V25.6343C111.689 25.8361 111.528 25.9975 111.337 25.9975H111.327H109.246C109.045 26.0076 108.884 25.8462 108.884 25.6545V25.6444V6.32535C108.884 6.12359 109.045 5.96217 109.236 5.96217H109.246H114.937C118.426 5.96217 120.819 8.1715 120.819 11.4603C120.819 13.6393 119.673 15.4653 117.561 16.5347C117.521 16.5448 117.501 16.5952 117.511 16.6356V16.6457L120.98 25.5435C121.05 25.7352 120.96 25.947 120.769 26.0177C120.718 26.0378 120.678 26.0479 120.628 26.0479H118.466C118.245 25.9975 118.054 25.8462 117.974 25.6343ZM114.716 14.8197C116.395 14.8197 117.893 13.6393 117.893 11.5713C117.893 9.71501 116.697 8.32283 114.716 8.32283H111.89C111.79 8.32283 111.719 8.40353 111.709 8.49433V14.6179C111.709 14.7188 111.8 14.8096 111.9 14.8096L114.716 14.8197Z" fill="currentColor"></path>
                                            </g>
                                        </svg>
                                    <a href='https://dashboard.pusher.com/apps' target='_blank' class='text-sm font-medium inline-flex gap-1'>@lang('app.generateCredentials')
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/>
                                            <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">@lang('superadmin.pusherChannelsDescription')</div>
                        </div>
                        </div>

                    @if ($pusherBroadcastStatus)

                        <!-- Pusher Channels (Live Update) Settings -->
                        <div class="mb-4 mt-4">
                            <div>
                                <x-label for="pusher_app_id" value="Pusher App ID" />
                                <x-input id="pusher_app_id" class="block mt-1 w-full" type="text" wire:model='pusher_app_id' />
                                <x-input-error for="pusher_app_id" class="mt-2" />
                            </div>
                            <div class="mt-3">
                                <x-label for="pusher_key" value="Pusher Key" />
                                <x-input-password id="pusher_key" class="block mt-1 w-full" type="text" wire:model='pusher_key' />
                                <x-input-error for="pusher_key" class="mt-2" />
                            </div>
                            <div class="mt-3">
                                <x-label for="pusher_secret" value="Pusher Secret" />
                                <x-input-password id="pusher_secret" class="block mt-1 w-full" type="text" wire:model='pusher_secret' />
                                <x-input-error for="pusher_secret" class="mt-2" />
                            </div>
                            <div class="mt-3">
                                <x-label for="pusher_cluster" value="Pusher Cluster" />
                                <x-input id="pusher_cluster" class="block mt-1 w-full" type="text" wire:model='pusher_cluster' />
                                <x-input-error for="pusher_cluster" class="mt-2" />
                            </div>
                        </div>
                    @endif
                </div>



                <div>
                    <x-button>@lang('app.save')</x-button>
                </div>
            </div>
        </form>
    </div>

</div>
