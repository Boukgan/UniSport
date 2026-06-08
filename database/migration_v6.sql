-- UniSport v6 migration
-- Prevent concurrent double-booking at the DB level (race-condition guard).
ALTER TABLE reservations
  ADD UNIQUE KEY uniq_booking (facility_id, booking_date, start_time);
