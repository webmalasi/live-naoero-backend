<!DOCTYPE html>
<html lang="en" class="h-full bg-[#07194f]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Sign In - Naoero Airlines</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                    },
                    colors: {
                        navy: {
                            DEFAULT: '#0b235d',
                            800: '#0c2666',
                            900: '#07194f',
                            950: '#05133d',
                        },
                        airline: {
                            gold: '#fbb034',
                            yellow: '#f59e0b',
                            blue: '#1652d4',
                            darkblue: '#0b235d',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .form-input-custom {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #0f172a;
            border-radius: 8px;
        }
        .form-input-custom:focus {
            background-color: #ffffff;
            border-color: #0b235d;
            outline: none;
            box-shadow: 0 0 0 2px rgba(11, 35, 93, 0.15);
        }
        .form-input-custom::placeholder {
            color: #94a3b8;
        }
    </style>
</head>
<body class="min-h-screen w-full bg-[#07194f] flex items-center justify-center p-4 relative overflow-hidden select-none">

    <!-- Background Layer -->
    <div class="fixed inset-0 bg-[#07194f] pointer-events-none"></div>

    <!-- Bottom Flowing Waves (Naoero Airlines Livery Style) -->
    <div class="fixed inset-x-0 bottom-0 w-full pointer-events-none z-0 overflow-hidden leading-none">
        <svg class="w-full h-44 sm:h-56 md:h-64 lg:h-72 block preserve-3d" viewBox="0 0 1600 320" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <!-- Deep Navy Base / Shading -->
            <path d="M-50,290 C300,230 600,320 1000,270 C1300,225 1500,285 1650,260 L1650,360 L-50,360 Z" fill="#040e2d" opacity="0.95" />
            
            <!-- Dark Blue Flowing Band -->
            <path d="M-50,270 C240,320 560,220 920,290 C1240,345 1480,250 1650,260 L1650,360 L-50,360 Z" fill="#092055" opacity="0.8" />
            
            <!-- Mid Navy Stream -->
            <path d="M-50,285 C320,185 700,305 1100,225 C1320,185 1500,220 1650,235 C1500,240 1320,210 1100,245 C700,325 300,205 -50,305 Z" fill="#0e3182" opacity="0.85" />
            
            <!-- Royal Blue Sweeping Ribbon -->
            <path d="M-50,310 C340,190 750,315 1160,235 C1380,195 1520,230 1650,245 C1520,255 1360,220 1150,260 C740,340 320,215 -50,335 Z" fill="#154ec1" opacity="0.95" />

            <!-- Cyan / Vibrant Blue Accent Highlight -->
            <path d="M-50,290 C380,325 760,200 1180,290 C1360,330 1500,300 1650,285 C1500,305 1350,340 1160,305 C750,215 360,340 -50,305 Z" fill="#1d63ed" opacity="0.3" />

            <!-- Signature Gold / Yellow Swoosh Ribbon -->
            <path d="M-50,270 C300,210 680,200 1040,260 C1270,300 1450,280 1650,225 C1450,292 1260,312 1030,272 C670,212 290,222 -50,282 Z" fill="#fbb034" />
        </svg>
    </div>

    <!-- Centered Login Card -->
    <div class="w-full max-w-[430px] bg-white rounded-2xl shadow-[0_25px_60px_rgba(0,0,0,0.45)] p-8 sm:p-9 relative z-10 my-6">
        
        <!-- Naoero Airlines Brand Logo -->
        <div class="flex justify-center mb-6">
            <img src="{{ asset('assets/logo.png') }}" alt="Naoero Airlines" class="h-16 sm:h-[72px] w-auto object-contain">
        </div>

        <!-- Heading -->
        <div class="mb-5">
            <h1 class="text-xl sm:text-[22px] font-bold text-[#07194f] tracking-tight">Admin sign in</h1>
        </div>

        <!-- Session & Error Alerts -->
        @if($errors->any())
            <div class="p-3 mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs flex items-start gap-2">
                <svg class="w-4 h-4 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="font-medium">
                    {{ $errors->first() }}
                </div>
            </div>
        @endif

        @if(session('info'))
            <div class="p-3 mb-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-xs flex items-start gap-2">
                <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div class="font-medium">
                    {{ session('info') }}
                </div>
            </div>
        @endif

        <!-- Login Form -->
        <form method="POST" action="{{ route('login.post') }}" class="space-y-4">
            @csrf

            <!-- Email Field -->
            <div>
                <label for="email" class="block text-[13px] font-medium text-slate-700 mb-1.5">Email</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="{{ old('email') }}" 
                       required 
                       autofocus 
                       placeholder="name@naoero-airlines.com"
                       class="form-input-custom w-full px-3.5 py-2.5 sm:py-3 text-sm font-normal">
            </div>

            <!-- Password Field -->
            <div>
                <label for="password" class="block text-[13px] font-medium text-slate-700 mb-1.5">Password</label>
                <div class="relative">
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder=""
                           class="form-input-custom w-full pl-3.5 pr-10 py-2.5 sm:py-3 text-sm font-normal">
                    
                    <!-- Password Visibility Toggle Button -->
                    <button type="button" 
                            id="togglePasswordBtn"
                            onclick="togglePasswordVisibility()" 
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none transition-colors"
                            aria-label="Toggle password visibility">
                        <!-- Eye Icon (Password Hidden) -->
                        <svg id="eyeIcon" class="w-4 h-4 sm:w-4.5 sm:h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <!-- Eye-Off Icon (Password Visible) -->
                        <svg id="eyeOffIcon" class="w-4 h-4 sm:w-4.5 sm:h-4.5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" 
                    class="w-full mt-5 py-3 px-4 bg-[#0b235d] hover:bg-[#07194f] active:bg-[#05133d] active:scale-[0.99] text-white font-semibold text-sm rounded-lg shadow-sm transition duration-150 flex items-center justify-center">
                <span>Sign in</span>
            </button>
        </form>

    </div>

    <!-- Toggle Password Visibility Script -->
    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
