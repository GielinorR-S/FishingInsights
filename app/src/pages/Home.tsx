import { Link } from 'react-router-dom'

export default function Home() {
  return (
    <div className="container mx-auto px-4 py-6 pb-20">
      <header className="page-header">
        <h1 className="page-title text-primary-600">FishingInsights</h1>
        <p className="page-subtitle">Fishing forecasts for Victorian anglers</p>
      </header>

      <div className="space-y-4">
        <div className="card">
          <h2 className="section-heading mb-3">Today's Best</h2>
          <p className="text-gray-600 text-sm">Coming soon</p>
        </div>

        <div className="card">
          <h2 className="section-heading mb-3">Favourites</h2>
          <p className="text-gray-600 text-sm">No favourites yet</p>
        </div>

        <Link to="/locations" className="btn-primary block text-center w-full">
          Browse Locations
        </Link>
      </div>
    </div>
  )
}

