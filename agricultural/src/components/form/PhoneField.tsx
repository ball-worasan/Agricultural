import { useState } from "react";
import TextField from "@mui/material/TextField";

interface PhoneFieldProps {
  label?: string;
  name?: string;
  required?: boolean;
  error?: boolean;
  helperText?: string;
  fullWidth?: boolean;
}

export default function PhoneField({
  label = "เบอร์โทรศัพท์",
  name = "phone",
  required = false,
  error = false,
  helperText = "ตัวอย่าง: 081-234-5678",
  fullWidth = true,
}: PhoneFieldProps) {
  const [phoneDisplay, setPhoneDisplay] = useState("");

  const handlePhoneChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const input = e.target.value.replace(/\D/g, ""); // เอาเฉพาะตัวเลข
    if (input.length <= 10) {
      const formatted = input.replace(/(\d{3})(\d{3})(\d{4})/, "$1-$2-$3");
      setPhoneDisplay(
        input.length > 6
          ? formatted
          : input.replace(/(\d{3})(\d{0,3})/, "$1-$2")
      );
    }
  };

  return (
    <TextField
      label={label}
      name={name}
      required={required}
      fullWidth={fullWidth}
      value={phoneDisplay}
      onChange={handlePhoneChange}
      inputMode="numeric"
      placeholder="0XX-XXX-XXXX"
      error={error}
      helperText={helperText}
    />
  );
}
