<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReadSmart Admin Dashboard</title>
    <!-- Gagamit tayo ng Tailwind CSS para mabilis at maganda ang design -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">

    <div class="max-w-6xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-black text-red-800">ReadSmart Admin Panel</h1>
            <span class="bg-yellow-400 text-black px-4 py-2 rounded-lg font-bold shadow-md">Pending Requests: {{ $pendingRequests->count() }}</span>
        </div>

        <!-- Success/Error Alerts -->
        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 shadow" role="alert">
                <p class="font-bold">Success!</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow" role="alert">
                <p class="font-bold">Notice</p>
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <div class="bg-white shadow-lg rounded-xl overflow-hidden border-2 border-black">
            <table class="min-w-full leading-normal">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-bold uppercase tracking-wider">Student Name</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-bold uppercase tracking-wider">LRN</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-bold uppercase tracking-wider">Grade & Section</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-left text-xs font-bold uppercase tracking-wider">Parent Email</th>
                        <th class="px-5 py-3 border-b-2 border-gray-200 text-center text-xs font-bold uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingRequests as $request)
                        <tr>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm font-bold">{{ $request->student_name }}</td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">{{ $request->lrn }}</td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">{{ $request->grade_level }} - {{ $request->section }}</td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-blue-600">{{ $request->parent_email }}</td>
                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                <div class="flex justify-center space-x-2">
                                    <!-- Approve Button Form -->
                                    <form action="{{ route('admin.approve', $request->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded border-2 border-black shadow">
                                            Approve
                                        </button>
                                    </form>

                                    <!-- Reject Button Form -->
                                    <form action="{{ route('admin.reject', $request->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded border-2 border-black shadow" onclick="return confirm('Are you sure you want to reject this request?')">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 border-b border-gray-200 bg-white text-center text-gray-500 font-bold text-lg">
                                No pending requests at the moment. 🎉
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>