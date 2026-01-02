import { Link, useLocation } from "react-router-dom";
import { HomeIcon, MapPinIcon, InfoIcon } from "./icons";

export default function BottomNav() {
  const location = useLocation();

  const isActive = (path: string) => {
    if (path === "/") {
      return location.pathname === "/";
    }
    return location.pathname.startsWith(path);
  };

  return (
    <nav className="fixed bottom-0 left-0 right-0 bg-gray-800/30 backdrop-blur-md border-t border-gray-600/30 shadow-lg z-50 safe-area-bottom">
      <div className="flex justify-around items-center h-16 max-w-2xl mx-auto pb-safe">
        <Link
          to="/"
          className={`flex flex-col items-center justify-center flex-1 h-full transition-colors ${
            isActive("/")
              ? "text-white drop-shadow-lg"
              : "text-gray-200 hover:text-white drop-shadow-md"
          }`}
          aria-label="Home"
        >
          <div
            className={`relative ${
              isActive("/") ? "scale-110" : "scale-100"
            } transition-transform`}
          >
            <HomeIcon className="w-6 h-6" />
            {isActive("/") && (
              <div className="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-white rounded-full drop-shadow-md" />
            )}
          </div>
          <span
            className={`text-xs mt-1 font-medium ${
              isActive("/")
                ? "font-semibold text-white drop-shadow-lg"
                : "font-normal text-gray-200 drop-shadow-md"
            }`}
          >
            Home
          </span>
        </Link>
        <Link
          to="/locations"
          className={`flex flex-col items-center justify-center flex-1 h-full transition-colors ${
            isActive("/locations")
              ? "text-white drop-shadow-lg"
              : "text-gray-200 hover:text-white drop-shadow-md"
          }`}
          aria-label="Locations"
        >
          <div
            className={`relative ${
              isActive("/locations") ? "scale-110" : "scale-100"
            } transition-transform`}
          >
            <MapPinIcon className="w-6 h-6" />
            {isActive("/locations") && (
              <div className="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-white rounded-full drop-shadow-md" />
            )}
          </div>
          <span
            className={`text-xs mt-1 font-medium ${
              isActive("/locations")
                ? "font-semibold text-white drop-shadow-lg"
                : "font-normal text-gray-200 drop-shadow-md"
            }`}
          >
            Locations
          </span>
        </Link>
        <Link
          to="/references"
          className={`flex flex-col items-center justify-center flex-1 h-full transition-colors ${
            isActive("/references")
              ? "text-white drop-shadow-lg"
              : "text-gray-200 hover:text-white drop-shadow-md"
          }`}
          aria-label="Info"
        >
          <div
            className={`relative ${
              isActive("/references") ? "scale-110" : "scale-100"
            } transition-transform`}
          >
            <InfoIcon className="w-6 h-6" />
            {isActive("/references") && (
              <div className="absolute -bottom-1 left-1/2 transform -translate-x-1/2 w-1 h-1 bg-white rounded-full drop-shadow-md" />
            )}
          </div>
          <span
            className={`text-xs mt-1 font-medium ${
              isActive("/references")
                ? "font-semibold text-white drop-shadow-lg"
                : "font-normal text-gray-200 drop-shadow-md"
            }`}
          >
            Info
          </span>
        </Link>
      </div>
    </nav>
  );
}
