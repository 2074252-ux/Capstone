
<div id="thresholdModal" class="hidden fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center p-4 z-50">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-slate-900">Edit Low Stock Threshold</h3>
            <button type="button" onclick="closeThresholdModal()" class="text-slate-400 hover:text-slate-600">×</button>
        </div>
        <p class="text-slate-900 mb-2">Set the stock level at which to receive low stock alerts for:</p>
        <p id="thresholdItemName" class="font-medium text-slate-900 mb-4"></p>
        
        <form id="thresholdForm" action="update_threshold.php" method="POST" class="space-y-4">
            <input type="hidden" name="item_id" id="thresholdItemId">
            <div>
                <label class="block text-sm font-medium text-slate-900 mb-1">Notify when stock reaches:</label>
                <input type="number" name="threshold" id="thresholdValue" min="1" required
                       class="w-full p-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-violet-500 text-slate-900">
            </div>
            
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" onclick="closeThresholdModal()"
                        class="px-4 py-2 bg-slate-100 text-slate-900 rounded-lg hover:bg-slate-200">
                    Cancel
                </button>
                <button type="submit"
                        class="px-4 py-2 bg-violet-600 text-white rounded-lg hover:bg-violet-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>