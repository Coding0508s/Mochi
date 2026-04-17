<?php

namespace App\GsBrochure\Http\Controllers\Api;

use App\GsBrochure\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Contact::orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => 'required|string']);
        $contact = Contact::create(['name' => $request->input('name')]);

        return response()->json(['id' => $contact->id, 'name' => $contact->name]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $contact = Contact::findOrFail($id);
        $contact->update($request->validate(['name' => 'required|string']));

        return response()->json(['success' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        Contact::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
