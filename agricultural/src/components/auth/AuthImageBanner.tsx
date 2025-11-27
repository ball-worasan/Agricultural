import Paper from "@mui/material/Paper";
import Typography from "@mui/material/Typography";

interface AuthImageBannerProps {
  title: string;
  imageUrl?: string;
}

export default function AuthImageBanner({
  title,
  imageUrl = "https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=800&auto=format&fit=crop",
}: AuthImageBannerProps) {
  return (
    <Paper
      sx={{
        height: { xs: 300, md: 520 },
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        bgcolor: "rgba(0,0,0,.04)",
        backgroundImage: `url("${imageUrl}")`,
        backgroundSize: "cover",
        backgroundPosition: "center",
        position: "relative",
        overflow: "hidden",
        "&::before": {
          content: '""',
          position: "absolute",
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          bgcolor: "rgba(0,0,0,0.3)",
        },
      }}
    >
      <Typography
        variant="h3"
        fontWeight={700}
        color="white"
        sx={{
          position: "relative",
          zIndex: 1,
          textAlign: "center",
          px: 2,
        }}
      >
        {title}
      </Typography>
    </Paper>
  );
}
