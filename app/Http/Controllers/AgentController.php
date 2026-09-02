<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            // Guest user - return empty collection
            $agents = collect();
        } else {
            $agents = $user->agents()->latest()->get();
        }

        if ($request->wantsJson()) {
            return response()->json($agents);
        }

        return view('ai-plus.agent-workspace.agents.index', [
            'agents' => $agents,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'is_shared' => 'boolean',
        ]);

        $agent = Auth::user()->agents()->create($data);

        if ($request->wantsJson()) {
            return response()->json($agent, 201);
        }

        return redirect()->route('ai-plus.agent-workspace.agents.index')
            ->with('success', 'Agent created successfully.');
    }

    public function show(Agent $agent)
    {
        $this->authorize('view', $agent);

        if (request()->wantsJson()) {
            return response()->json($agent);
        }

        return view('ai-plus.agent-workspace.agents.show', ['agent' => $agent]);
    }

    public function update(Request $request, Agent $agent)
    {
        $this->authorize('update', $agent);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'system_prompt' => 'nullable|string',
            'is_shared' => 'boolean',
        ]);

        $agent->update($data);

        if ($request->wantsJson()) {
            return response()->json($agent);
        }

        return redirect()->route('ai-plus.agent-workspace.agents.index')
            ->with('success', 'Agent updated successfully.');
    }

    public function destroy(Request $request, Agent $agent)
    {
        $this->authorize('delete', $agent);

        $agent->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Agent deleted']);
        }

        return redirect()->route('ai-plus.agent-workspace.agents.index')
            ->with('success', 'Agent deleted successfully.');
    }
}