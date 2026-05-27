<!-- Error and Success Modal Handler -->
<!-- Error Modal -->
<div id="errorModal" class="fixed inset-0 bg-slate-900/30 backdrop-blur-sm hidden items-center justify-center z-[9999] p-4" onclick="if(event.target === this) closeErrorModal()">
    <div class="bg-white rounded-xl w-full max-w-md shadow-2xl border border-gray-200 animate-fadeIn">
        <div class="p-6 border-b">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl font-bold text-gray-800" id="errorModalTitle">Error</h2>
                </div>
                <button onclick="closeErrorModal()" class="ml-3 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        <div class="p-6">
            <p class="text-gray-700 whitespace-pre-wrap" id="errorModalMessage">An error occurred.</p>
        </div>
        <div class="p-6 border-t flex justify-end gap-3">
            <button onclick="closeErrorModal()" class="px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 font-semibold">OK</button>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 bg-slate-900/30 backdrop-blur-sm hidden items-center justify-center z-[9999] p-4" onclick="if(event.target === this) closeSuccessModal()">
    <div class="bg-white rounded-xl w-full max-w-md shadow-2xl border border-gray-200 animate-fadeIn">
        <div class="p-6 border-b">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-xl font-bold text-gray-800" id="successModalTitle">Success</h2>
                </div>
                <button onclick="closeSuccessModal()" class="ml-3 text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        <div class="p-6">
            <p class="text-gray-700 whitespace-pre-wrap" id="successModalMessage">Operation completed successfully.</p>
        </div>
        <div class="p-6 border-t flex justify-end gap-3">
            <button onclick="closeSuccessModal()" class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 font-semibold">OK</button>
        </div>
    </div>
</div>

<style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
    .animate-fadeIn {
        animation: fadeIn 0.2s ease-out;
    }
</style>

