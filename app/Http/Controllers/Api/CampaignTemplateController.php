<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Traits\MapsCamelCaseFields;
use App\Models\CampaignTemplate;
use App\Exceptions\AppError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignTemplateController extends Controller
{
    use MapsCamelCaseFields;
    public function getTemplates(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => CampaignTemplate::latest()->paginate($request->input('per_page', 20))]);
    }

    public function getTemplateById(string $id): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => CampaignTemplate::findOrFail($id)]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function createTemplate(Request $request): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'contentHtml' => 'content_html',
            ]);
            $request->replace($input);

            $validated = $request->validate([
                'name' => 'required|string|max:255', 'description' => 'nullable|string',
                'category' => 'nullable|string|max:100', 'thumbnail' => 'nullable|string',
                'content_html' => 'nullable|string', 'variables' => 'nullable|string', 'status' => 'nullable|in:ACTIVE,INACTIVE',
            ]);
            $validated['created_by'] = $request->user()->id ?? null;
            $template = CampaignTemplate::create($validated);
            return response()->json(['success' => true, 'data' => $template], 201);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }

    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        try {
            $input = $this->mapCamelCase($request->all(), [
                'contentHtml' => 'content_html',
            ]);

            $template = CampaignTemplate::findOrFail($id);
            $template->update(collect($input)->only([
                'name', 'description', 'category', 'thumbnail', 'content_html', 'variables', 'status'
            ])->toArray());
            return response()->json(['success' => true, 'data' => $template]);
        } catch (AppError $e) { return $e->render(); }
    }

    public function deleteTemplate(string $id): JsonResponse
    {
        try {
            CampaignTemplate::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => 'Template deleted']);
        } catch (AppError $e) { return $e->render(); }
    }

    public function getDefaultTemplates(Request $request): JsonResponse
    {
        $defaults = CampaignTemplate::where('is_default', true)->get();
        if ($defaults->isEmpty()) {
            $seeds = [
                ['name' => 'Welcome Email', 'content_html' => '<div style="font-family:Arial;padding:20px"><h2>Welcome!</h2><p>Thank you for joining us. We\'re excited to have you on board.</p></div>'],
                ['name' => 'Order Confirmation', 'content_html' => '<div style="font-family:Arial;padding:20px"><h2>Order Confirmed</h2><p>Your order has been placed successfully.</p></div>'],
                ['name' => 'Password Reset', 'content_html' => '<div style="font-family:Arial;padding:20px"><h2>Reset Your Password</h2><p>Click the link below to reset your password.</p></div>'],
                ['name' => 'Newsletter', 'content_html' => '<div style="font-family:Arial;padding:20px"><h2>Monthly Newsletter</h2><p>Here\'s what\'s new this month.</p></div>'],
                ['name' => 'Promotional Offer', 'content_html' => '<div style="font-family:Arial;padding:20px"><h2>Special Offer</h2><p>Check out our latest deals and discounts.</p></div>'],
            ];
            foreach ($seeds as $seed) {
                CampaignTemplate::create(['name' => $seed['name'], 'description' => "Default {$seed['name']} template", 'content_html' => $seed['content_html'], 'category' => 'SYSTEM', 'is_default' => true, 'status' => 'ACTIVE']);
            }
            $defaults = CampaignTemplate::where('is_default', true)->get();
        }
        return response()->json(['success' => true, 'data' => $defaults]);
    }

    public function renderTemplate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate(['template_id' => 'required|string', 'variables' => 'nullable|array']);
            $template = CampaignTemplate::findOrFail($validated['template_id']);
            $html = $template->content_html ?? '<p>Template: ' . $template->name . '</p>';
            return response()->json(['success' => true, 'data' => ['html' => $html, 'subject' => $template->name]]);
        } catch (\Exception $e) { return response()->json(['success' => false, 'message' => $e->getMessage()], 422); }
    }
}
