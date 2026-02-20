
<div id="editModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center p-4 z-50">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
    <form action="update_item.php" method="POST" id="editItemForm">
      <div class="flex justify-between items-center p-5 border-b border-gray-200">
        <h5 class="text-xl font-semibold text-slate-800">Edit Item</h5>
        <button type="button" class="closeModal text-gray-400 hover:text-gray-600" aria-label="Close edit modal">&times;</button>
      </div>
      <div class="p-6 space-y-4">
        <input type="hidden" name="id" id="edit_id">
        <label class="block text-sm font-medium text-gray-700">Barcode:</label>
        <input type="text" name="barcode" id="edit_barcode" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">

        <label class="block text-sm font-medium text-gray-700 mt-3">Name:</label>
        <input type="text" name="name" id="edit_name" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">

        <label class="block text-sm font-medium text-gray-700 mt-3">Category:</label>
        <select name="category_id" id="edit_category_id" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">
          <?php
            // categories are available from items.php scope when included
            foreach ($categories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>

        <label class="block text-sm font-medium text-gray-700 mt-3">Quantity:</label>
        <input type="number" name="quantity" id="edit_quantity" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">

        <label class="block text-sm font-medium text-gray-700 mt-3">Price:</label>
        <input type="number" step="0.01" name="price" id="edit_price" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">

        <label class="block text-sm font-medium text-gray-700 mt-3">Expiration Date:</label>
        <input type="date" name="expiration_date" id="edit_expiration_date" class="mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:ring-sky-500">
      </div>
      <div class="flex justify-end p-5 border-t border-gray-200">
        <button type="submit" class="px-4 py-2 rounded-full bg-sky-500 text-white hover:bg-sky-600 mr-2">Save</button>
        <button type="button" class="closeModal px-4 py-2 rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300">Cancel</button>
      </div>
    </form>
  </div>
</div>