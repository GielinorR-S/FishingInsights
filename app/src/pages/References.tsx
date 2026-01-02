import { ChevronRightIcon } from '../components/icons'

export default function References() {
  return (
    <div className="container">
      <header className="page-header">
        <h1 className="page-title">References & Info</h1>
        <p className="page-subtitle">Data sources, regulations, and important information</p>
      </header>

      <div className="space-y-6">
        <section className="card">
          <h2 className="section-heading mb-4">Data Sources</h2>
          <div className="space-y-4 text-sm">
            <div className="pb-4 border-b border-gray-200">
              <p className="font-bold text-base text-gray-900 mb-1">Weather Data</p>
              <p className="text-gray-700 mb-2">Open-Meteo (free, no API key required)</p>
              <a href="https://open-meteo.com" target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:text-primary-700 font-medium text-xs inline-flex items-center gap-1">
                open-meteo.com →
              </a>
            </div>
            <div className="pb-4 border-b border-gray-200">
              <p className="font-bold text-base text-gray-900 mb-1">Sunrise/Sunset Data</p>
              <p className="text-gray-700">Open-Meteo (free, no API key required)</p>
            </div>
            <div>
              <p className="font-bold text-base text-gray-900 mb-1">Tide Data</p>
              <p className="text-gray-700 mb-2">WorldTides.info (credit-based, low-cost) or mock data fallback</p>
              <a href="https://www.worldtides.info" target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:text-primary-700 font-medium text-xs inline-flex items-center gap-1">
                worldtides.info →
              </a>
            </div>
          </div>
        </section>

        <section className="card">
          <h2 className="section-heading mb-4">Fishing Regulations</h2>
          <p className="text-base text-gray-800 mb-4 leading-relaxed">
            Always check current Victorian fishing regulations before heading out.
          </p>
          <p className="text-sm text-gray-700 mb-3">
            For the latest rules, bag limits, and seasonal closures, visit:
          </p>
          <a href="https://vfa.vic.gov.au" target="_blank" rel="noopener noreferrer" className="btn-primary inline-flex items-center gap-2">
            Victorian Fisheries Authority
            <ChevronRightIcon className="w-4 h-4" size={16} />
          </a>
        </section>

        <section className="card">
          <h2 className="section-heading mb-4">Disclaimer</h2>
          <p className="text-base text-gray-800 leading-relaxed">
            FishingInsights provides informational forecasts only. Weather, tides, and fishing conditions can change rapidly.
            Always prioritize safety, check current conditions before heading out, and follow all local regulations.
          </p>
        </section>
      </div>
    </div>
  )
}
