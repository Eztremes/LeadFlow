import { useEffect, useState } from "react";
import axios from "axios";
import {
  UploadCloud,
  FileSpreadsheet,
  CheckCircle2,
  AlertCircle,
  Download,
  Loader2,
} from "lucide-react";

const API = (
  import.meta.env.VITE_API_BASE_URL || "http://127.0.0.1:8000"
).replace(/\/+$/, "") + "/api";

export default function App() {
  const [file, setFile] = useState(null);
  const [email, setEmail] = useState("");
  const [uploading, setUploading] = useState(false);
  const [importData, setImportData] = useState(null);
  const [error, setError] = useState("");

  async function handleUpload(e) {
    e.preventDefault();

    if (!file || !email) {
      setError("Please select a CSV file and enter your email.");
      return;
    }

    setError("");
    setImportData(null);
    setUploading(true);

    const formData = new FormData();
    formData.append("file", file);
    formData.append("email", email);

    try {
      const response = await axios.post(
        `${API}/imports`,
        formData
      );

      setImportData({
        id: response.data.import_id,
        status: response.data.status,
        total_records: 0,
        success_count: 0,
        failed_count: 0,
        progress: 0,
      });
    } catch (err) {
      setError(
        err.response?.data?.message ||
        "Upload failed. Please try again."
      );
    } finally {
      setUploading(false);
    }
  }

  useEffect(() => {
    if (!importData?.id) return;

    if (
      importData.status === "completed" ||
      importData.status === "failed"
    ) {
      return;
    }

    const timer = setInterval(async () => {
      try {
        const response = await axios.get(
          `${API}/imports/${importData.id}`
        );

        setImportData(response.data);
      } catch (err) {
        console.error("Status polling failed:", err);
      }
    }, 1500);

    return () => clearInterval(timer);
  }, [importData?.id, importData?.status]);

  const finished = importData?.status === "completed";
  const failed = importData?.status === "failed";

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center gap-3 px-6 py-5">
          <div className="rounded-xl bg-indigo-600 p-2 text-white">
            <FileSpreadsheet size={23} />
          </div>
          <div>
            <h1 className="text-xl font-bold">LeadFlow</h1>
            <p className="text-xs text-slate-500">
              Large CSV Lead Import System
            </p>
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-5xl px-6 py-12">
        <div className="mb-9">
          <h2 className="text-3xl font-bold tracking-tight">
            Import your leads
          </h2>
          <p className="mt-2 text-slate-500">
            Upload large CSV files and track processing in real time.
          </p>
        </div>

        <div className="grid gap-7 lg:grid-cols-5">
          <section className="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm lg:col-span-3">
            <h3 className="mb-6 text-lg font-semibold">
              Upload CSV
            </h3>

            <form onSubmit={handleUpload} className="space-y-6">
              <label className="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-5 py-12 text-center transition hover:bg-indigo-50">
                <UploadCloud
                  size={36}
                  className="mb-4 text-indigo-600"
                />

                <span className="font-medium text-slate-800">
                  {file ? file.name : "Choose a CSV file"}
                </span>

                <span className="mt-2 text-sm text-slate-500">
                  CSV files up to 100 MB
                </span>

                <input
                  type="file"
                  accept=".csv,text/csv"
                  className="hidden"
                  onChange={(e) =>
                    setFile(e.target.files?.[0] || null)
                  }
                />
              </label>

              <div>
                <label className="mb-2 block text-sm font-medium">
                  Notification email
                </label>

                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="you@example.com"
                  required
                  className="w-full rounded-lg border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                />
              </div>

              {error && (
                <p className="flex items-center gap-2 text-sm text-red-600">
                  <AlertCircle size={16} />
                  {error}
                </p>
              )}

              <button
                type="submit"
                disabled={uploading || !file}
                className="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-3 font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                {uploading ? (
                  <>
                    <Loader2 size={18} className="animate-spin" />
                    Uploading...
                  </>
                ) : (
                  <>
                    <UploadCloud size={18} />
                    Start Import
                  </>
                )}
              </button>
            </form>
          </section>

          <section className="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm lg:col-span-2">
            <h3 className="mb-6 text-lg font-semibold">
              Import status
            </h3>

            {!importData ? (
              <div className="flex min-h-64 flex-col items-center justify-center text-center">
                <FileSpreadsheet
                  size={42}
                  className="mb-4 text-slate-300"
                />
                <p className="font-medium text-slate-600">
                  No active import
                </p>
                <p className="mt-2 text-sm text-slate-400">
                  Upload a CSV to view progress.
                </p>
              </div>
            ) : (
              <div className="space-y-6">
                <div className="flex items-center gap-2">
                  {finished ? (
                    <CheckCircle2 className="text-green-600" />
                  ) : failed ? (
                    <AlertCircle className="text-red-600" />
                  ) : (
                    <Loader2 className="animate-spin text-indigo-600" />
                  )}

                  <span className="font-semibold capitalize">
                    {importData.status}
                  </span>
                </div>

                <div>
                  <div className="mb-2 flex justify-between text-sm">
                    <span className="text-slate-500">
                      Processing progress
                    </span>
                    <span className="font-semibold">
                      {importData.progress ?? 0}%
                    </span>
                  </div>

                  <div className="h-3 overflow-hidden rounded-full bg-slate-100">
                    <div
                      className="h-full rounded-full bg-indigo-600 transition-all duration-500"
                      style={{
                        width: `${Math.min(
                          100,
                          importData.progress ?? 0
                        )}%`,
                      }}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-3">
                  {[
                    ["Total", importData.total_records],
                    ["Success", importData.success_count],
                    ["Failed", importData.failed_count],
                  ].map(([label, value]) => (
                    <div
                      key={label}
                      className="rounded-lg bg-slate-50 p-3 text-center"
                    >
                      <p className="text-xs text-slate-500">
                        {label}
                      </p>
                      <p className="mt-1 text-xl font-bold">
                        {value ?? 0}
                      </p>
                    </div>
                  ))}
                </div>

                {finished && importData.failed_count > 0 && (
                  <a
                    href={`${API}/imports/${importData.id}/failed-csv`}
                    className="flex items-center justify-center gap-2 rounded-lg border border-indigo-200 px-4 py-3 font-medium text-indigo-600 hover:bg-indigo-50"
                  >
                    <Download size={18} />
                    Download failed CSV
                  </a>
                )}

                {failed && (
                  <p className="text-sm text-red-600">
                    {importData.error_message ||
                      "Import processing failed."}
                  </p>
                )}
              </div>
            )}
          </section>
        </div>

        <p className="mt-10 text-center text-sm text-slate-400">
          LeadFlow · React + Laravel + MySQL
        </p>
      </main>
    </div>
  );
}
