<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminAudit;
use App\Http\Controllers\Controller;
use App\Models\NotificationTemplate;
use Illuminate\Http\Request;

/**
 * PHASE 2 - Penyunting template surel.
 *
 * Kode template tidak dapat diubah karena dirujuk langsung oleh kode program.
 * Yang dapat disunting hanya nama, subjek, isi, dan status aktifnya.
 */
class NotificationTemplateController extends Controller
{
    use LogsAdminAudit;

    public function index()
    {
        return view('admin.notification-templates', [
            'templates' => NotificationTemplate::orderBy('code')->get(),
            'activeCount' => NotificationTemplate::where('is_active', true)->count(),
            'totalCount' => NotificationTemplate::count(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $template = NotificationTemplate::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'is_active' => ['nullable'],
        ]);

        $template->update([
            'name' => $validated['name'],
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit(
            'template.updated',
            "Memperbarui template surel \"{$template->name}\" ({$template->code}).",
            $template
        );

        return back()->with('success', "Template \"{$template->name}\" berhasil diperbarui.");
    }
}
