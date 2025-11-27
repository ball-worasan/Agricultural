"use client";

import { memo } from "react";
import Container, { ContainerProps } from "@mui/material/Container";
import Header from "@/components/Header";
import Footer from "@/components/Footer";

interface PageContainerProps extends ContainerProps {
  children: React.ReactNode;
  showHeader?: boolean;
  showFooter?: boolean;
}

function PageContainer({
  children,
  showHeader = true,
  showFooter = false,
  maxWidth = "lg",
  ...props
}: PageContainerProps) {
  return (
    <>
      {showHeader && <Header />}
      <Container component="main" maxWidth={maxWidth} {...props}>
        {children}
      </Container>
      {showFooter && <Footer />}
    </>
  );
}

export default memo(PageContainer);
