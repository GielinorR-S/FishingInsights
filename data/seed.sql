-- FishingInsights Database Seed Data
-- Victoria locations and species rules

-- Locations (20+ Victorian fishing locations)
INSERT OR IGNORE INTO locations (name, region, latitude, longitude, timezone, description) VALUES
('Port Phillip Bay', 'Melbourne', -37.8500, 144.9500, 'Australia/Melbourne', 'Large bay with diverse fishing opportunities'),
('Western Port', 'Mornington Peninsula', -38.3500, 145.2500, 'Australia/Melbourne', 'Productive bay fishing'),
('Gippsland Lakes', 'Gippsland', -37.8000, 147.6000, 'Australia/Melbourne', 'Estuary and lake fishing'),
('Corner Inlet', 'Gippsland', -38.7000, 146.2000, 'Australia/Melbourne', 'Coastal inlet fishing'),
('Portland', 'South West', -38.3500, 141.6000, 'Australia/Melbourne', 'Deep sea and rock fishing'),
('Warrnambool', 'South West', -38.3833, 142.4833, 'Australia/Melbourne', 'Coastal fishing'),
('Apollo Bay', 'Great Ocean Road', -38.7500, 143.6667, 'Australia/Melbourne', 'Surf and rock fishing'),
('Lorne', 'Great Ocean Road', -38.5333, 143.9833, 'Australia/Melbourne', 'Coastal fishing'),
('Torquay', 'Great Ocean Road', -38.3333, 144.3167, 'Australia/Melbourne', 'Surf fishing'),
('Geelong', 'Geelong', -38.1500, 144.3500, 'Australia/Melbourne', 'Bay and estuary fishing'),
('Werribee River', 'Melbourne', -37.9000, 144.6667, 'Australia/Melbourne', 'River fishing'),
('Yarra River', 'Melbourne', -37.8167, 144.9500, 'Australia/Melbourne', 'Urban river fishing'),
('Maribyrnong River', 'Melbourne', -37.7833, 144.9000, 'Australia/Melbourne', 'River fishing'),
('Mornington Pier', 'Mornington Peninsula', -38.2167, 145.0333, 'Australia/Melbourne', 'Pier fishing'),
('Rye Pier', 'Mornington Peninsula', -38.3667, 144.8167, 'Australia/Melbourne', 'Pier fishing'),
('Sorrento Pier', 'Mornington Peninsula', -38.3333, 144.7333, 'Australia/Melbourne', 'Pier fishing'),
('Lakes Entrance', 'Gippsland', -37.8833, 147.9833, 'Australia/Melbourne', 'Estuary fishing'),
('Mallacoota', 'Gippsland', -37.5500, 149.7500, 'Australia/Melbourne', 'Coastal and estuary fishing'),
('Wilsons Promontory', 'Gippsland', -39.0333, 146.3000, 'Australia/Melbourne', 'Remote coastal fishing'),
('San Remo', 'Gippsland', -38.5167, 145.3667, 'Australia/Melbourne', 'Bridge and coastal fishing'),
('Inverloch', 'Gippsland', -38.6333, 145.7167, 'Australia/Melbourne', 'Estuary fishing'),
('Venus Bay', 'Gippsland', -38.6833, 145.8333, 'Australia/Melbourne', 'Surf fishing'),
('Phillip Island', 'Mornington Peninsula', -38.4667, 145.2333, 'Australia/Melbourne', 'Island fishing'),
('French Island', 'Western Port', -38.3333, 145.3333, 'Australia/Melbourne', 'Island fishing');

-- Species Rules (6+ common Victorian species)
INSERT OR IGNORE INTO species_rules (species_id, common_name, scientific_name, season_start_month, season_end_month, preferred_water_temp_min, preferred_water_temp_max, preferred_wind_max, preferred_conditions, preferred_tide_state, gear_bait, gear_lure, gear_line_weight, gear_leader, gear_rig, description) VALUES
('snapper', 'Snapper', 'Pagrus auratus', 11, 3, 16, 22, 25, 'calm, clear', 'rising', 'pilchards,squid,garfish', 'soft plastics,metal lures', '8-15lb', '10-20lb', 'paternoster', 'Popular game fish, best in warmer months'),
('whiting', 'King George Whiting', 'Sillaginodes punctatus', 10, 4, 14, 20, 20, 'calm', 'any', 'pipis,worm,garfish', 'soft plastics', '4-8lb', '6-12lb', 'running sinker', 'Delicate fish, light tackle recommended'),
('squid', 'Southern Calamari', 'Sepioteuthis australis', 1, 12, 12, 22, 20, 'calm, clear', 'any', 'jigs', 'squid jigs', '6-12lb', '8-15lb', 'jig', 'Year-round, best in calm conditions'),
('flathead', 'Dusky Flathead', 'Platycephalus fuscus', 1, 12, 14, 24, 25, 'calm', 'any', 'pilchards,garfish', 'soft plastics,hardbody lures', '6-10lb', '8-15lb', 'running sinker', 'Common year-round, versatile'),
('bream', 'Black Bream', 'Acanthopagrus butcheri', 1, 12, 12, 22, 20, 'calm', 'rising', 'worm,prawn,bread', 'soft plastics', '4-8lb', '6-12lb', 'paternoster', 'Estuary specialist, year-round'),
('aussie_salmon', 'Australian Salmon', 'Arripis trutta', 3, 11, 14, 20, 30, 'any', 'any', 'pilchards,garfish', 'metal lures,poppers', '10-20lb', '15-30lb', 'running sinker', 'Strong fighting fish, surf and bay');

