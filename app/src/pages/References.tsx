import { useState, useEffect } from 'react'
import { getHealth, type HealthResponse } from '../services/api'

export default function References() {
  const [health, setHealth] = useState<HealthResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    loadHealth()
  }, [])

  const loadHealth = async () => {
    try {
      setLoading(true)
      setError(null)
      const data = await getHealth()
      setHealth(data)
    } catch (err) {
      setError('Failed to load system information')
      console.error('Error loading health:', err)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="container mx-auto px-4 py-6 pb-20">
      <header className="page-header">
        <h1 className="page-title">References & Info</h1>
        <p className="page-subtitle">System information and data sources</p>
      </header>

      <div className="space-y-6">
        <section className="card">
          <h2 className="section-heading">System Health</h2>
          {loading && (
            <div className="text-center py-4">
              <div className="spinner mx-auto mb-3"></div>
              <p className="text-gray-600 text-sm">Loading system information...</p>
            </div>
          )}
          {error && (
            <div className="banner banner-error">
              <p>{error}</p>
              <button onClick={loadHealth} className="btn-primary mt-2">
                Retry
              </button>
            </div>
          )}
          {health && !loading && (
            <div className="space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <p className="text-sm font-medium text-gray-600">PHP Version</p>
                  <p className="text-base font-semibold text-gray-900">{health.php_version}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-600">Timezone</p>
                  <p className="text-base font-semibold text-gray-900">{health.timezone}</p>
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-600">PDO SQLite</p>
                  <p className="text-base font-semibold text-gray-900">
                    {health.has_pdo_sqlite ? (
                      <span className="text-green-600">✓ Available</span>
                    ) : (
                      <span className="text-red-600">✗ Not Available</span>
                    )}
                  </p>
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-600">Database Write</p>
                  <p className="text-base font-semibold text-gray-900">
                    {health.can_write_db ? (
                      <span className="text-green-600">✓ Writable</span>
                    ) : (
                      <span className="text-red-600">✗ Not Writable</span>
                    )}
                  </p>
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-600">Cache Write</p>
                  <p className="text-base font-semibold text-gray-900">
                    {health.can_write_cache ? (
                      <span className="text-green-600">✓ Writable</span>
                    ) : (
                      <span className="text-red-600">✗ Not Writable</span>
                    )}
                  </p>
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-600">Last Check</p>
                  <p className="text-base font-semibold text-gray-900">
                    {new Date(health.timestamp).toLocaleString()}
                  </p>
                </div>
              </div>
              {health.sqlite_db_path && (
                <div className="pt-2 border-t border-gray-200">
                  <p className="text-sm font-medium text-gray-600">Database Path</p>
                  <p className="text-xs text-gray-500 font-mono break-all">{health.sqlite_db_path}</p>
                </div>
              )}
            </div>
          )}
        </section>

        <section className="card">
          <h2 className="section-heading">Data Sources</h2>
          <div className="space-y-3 text-sm text-gray-700">
            <div>
              <p className="font-semibold">Weather Data</p>
              <p>Open-Meteo (free, no API key required)</p>
              <p className="text-xs text-gray-500 mt-1">
                <a href="https://open-meteo.com" target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:underline">
                  open-meteo.com
                </a>
              </p>
            </div>
            <div>
              <p className="font-semibold">Sunrise/Sunset Data</p>
              <p>Open-Meteo (free, no API key required)</p>
            </div>
            <div>
              <p className="font-semibold">Tide Data</p>
              <p>WorldTides.info (credit-based, low-cost) or mock data fallback</p>
              <p className="text-xs text-gray-500 mt-1">
                <a href="https://www.worldtides.info" target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:underline">
                  worldtides.info
                </a>
              </p>
            </div>
          </div>
        </section>

        <section className="card">
          <h2 className="section-heading">Fishing Regulations</h2>
          <p className="text-sm text-gray-700 mb-3">
            Always check current Victorian fishing regulations before heading out.
          </p>
          <p className="text-sm text-gray-700">
            For the latest rules, bag limits, and seasonal closures, visit:
          </p>
          <p className="text-xs text-gray-500 mt-2">
            <a href="https://vfa.vic.gov.au" target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:underline">
              Victorian Fisheries Authority
            </a>
          </p>
        </section>

        <section className="card">
          <h2 className="section-heading">Disclaimer</h2>
          <p className="text-sm text-gray-700">
            FishingInsights provides informational forecasts only. Weather, tides, and fishing conditions can change rapidly.
            Always prioritize safety, check current conditions before heading out, and follow all local regulations.
          </p>
        </section>
      </div>
    </div>
  )
}

